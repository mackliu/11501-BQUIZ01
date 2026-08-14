<?php
/**
 * 資料存取層
 * ---------------------------------------------------------------
 * 本檔於 2026-08-14 的安全修復中完全改寫。
 *
 * 修復項目：
 *   A05-1  SQL Injection —— 全面改用 prepared statement，
 *          並以「資料表 / 欄位白名單」限制可操作的範圍。
 *   A02-1  資料庫憑證 —— 移出程式碼，改由 api/config.php 提供，
 *          並改用最小權限帳號（不再使用 root / 空密碼）。
 *   A02-3  移除除錯用的 echo $sql。
 *   A10-1  PDO 改為 ERRMODE_EXCEPTION，錯誤寫入日誌而非輸出到畫面。
 *   A05-6  移除可變變數 ${ucfirst($table)}，改用 table() 白名單查表。
 *
 * 設計原則：
 *   - 欄位名永遠來自程式碼中的 SCHEMA 常數，永遠不來自使用者輸入。
 *   - 欄位值永遠透過具名參數繫結，永遠不進入 SQL 字串。
 *   - LIMIT / OFFSET 以 (int) 強制轉型後才組進 SQL（整數無法注入）。
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * PDO 連線的單一持有者。
 * 舊版每 new DB() 就開一條連線（共 9 條），現在全站共用一條。
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = app_config();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['db_host'],
            $cfg['db_name'],
            $cfg['db_charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
                // 失敗時丟例外，不再回傳 false 讓後續 ->fetch() 觸發 Fatal error
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // 關閉模擬預備語句，確保由資料庫端真正做參數繫結
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            app_log('db.connect_failed', ['error' => $e->getMessage()]);
            app_fail(
                500,
                '資料庫連線失敗。請確認已執行 db/create_app_user.sql 建立 db21_app 帳號，'
                . '且 api/config.php 中的帳密正確。'
            );
        }

        return self::$pdo;
    }
}

final class DB
{
    /**
     * 資料表與「可寫入欄位」白名單。
     * id 為主鍵，另外處理，不列在這裡。
     * 任何不在清單中的欄位名都會被拒絕（fail closed）。
     */
    private const SCHEMA = [
        'ad'     => ['text', 'sh'],
        'admin'  => ['acc', 'pw'],
        'bottom' => ['bottom'],
        'image'  => ['img', 'sh'],
        'menu'   => ['href', 'text', 'sh', 'main_id'],
        'mvim'   => ['img', 'sh'],
        'news'   => ['text', 'sh'],
        'title'  => ['img', 'text', 'sh'],
        'total'  => ['total'],
    ];

    private PDO $pdo;
    private string $table;

    /** @var string[] 本表允許出現在 WHERE / SET 的欄位（含 id） */
    private array $columns;

    public function __construct(string $table)
    {
        if (!isset(self::SCHEMA[$table])) {
            throw new InvalidArgumentException("unknown table: {$table}");
        }
        $this->table   = $table;
        $this->columns = array_merge(['id'], self::SCHEMA[$table]);
        $this->pdo     = Database::pdo();
    }

    /** 本表允許「寫入」的欄位（不含 id） */
    public function writableColumns(): array
    {
        return self::SCHEMA[$this->table];
    }

    // ---------------------------------------------------------------
    // 查詢
    // ---------------------------------------------------------------

    /**
     * @param array $cond ['欄位' => 值, ...]，以 AND 串接
     * @param array $opt  ['order'=>欄位, 'dir'=>'ASC|DESC', 'limit'=>int, 'offset'=>int]
     */
    public function all(array $cond = [], array $opt = []): array
    {
        [$where, $bind] = $this->buildWhere($cond);
        $sql = "SELECT * FROM `{$this->table}`{$where}" . $this->buildOrderLimit($opt);

        $st = $this->pdo->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll();
    }

    public function count(array $cond = []): int
    {
        [$where, $bind] = $this->buildWhere($cond);

        $st = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->table}`{$where}");
        $st->execute($bind);
        return (int) $st->fetchColumn();
    }

    /**
     * 取單筆。
     * @param int|array $arg 整數視為主鍵；陣列視為查詢條件
     * @return array|null 查無資料回傳 null（舊版回傳 false，容易被誤當陣列取值）
     */
    public function find(int|array $arg): ?array
    {
        $cond = is_array($arg) ? $arg : ['id' => $arg];
        [$where, $bind] = $this->buildWhere($cond);

        $st = $this->pdo->prepare("SELECT * FROM `{$this->table}`{$where} LIMIT 1");
        $st->execute($bind);

        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    // ---------------------------------------------------------------
    // 寫入
    // ---------------------------------------------------------------

    public function insert(array $data): int
    {
        $data = $this->filterWritable($data);
        if ($data === []) {
            throw new InvalidArgumentException('insert: no writable column supplied');
        }

        $cols   = array_keys($data);
        $marks  = array_map(static fn(string $c): string => ':' . $c, $cols);
        $sql    = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table,
            implode('`,`', $cols),
            implode(',', $marks)
        );

        $st = $this->pdo->prepare($sql);
        $st->execute($this->bindable($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): int
    {
        $data = $this->filterWritable($data);
        if ($data === []) {
            return 0; // 沒有可更新的欄位，視為無操作
        }

        $set = implode(', ', array_map(
            static fn(string $c): string => "`{$c}` = :{$c}",
            array_keys($data)
        ));

        $st = $this->pdo->prepare("UPDATE `{$this->table}` SET {$set} WHERE `id` = :__id");
        $st->execute($this->bindable($data) + [':__id' => $id]);

        return $st->rowCount();
    }

    /**
     * 相容舊呼叫方式：有 id 就更新、沒有就新增。
     * 未知欄位會被直接忽略（例如表單裡的 _token）。
     */
    public function save(array $data): int
    {
        if (isset($data['id']) && (int) $data['id'] > 0) {
            $id = (int) $data['id'];
            unset($data['id']);
            $this->update($id, $data);
            return $id;
        }
        return $this->insert($data);
    }

    public function del(int $id): int
    {
        $st = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        $st->execute([':id' => $id]);
        return $st->rowCount();
    }

    /**
     * 以資料庫端的原子運算遞增欄位，避免「讀取→加一→寫回」的競態。
     */
    public function increment(int $id, string $column, int $step = 1): int
    {
        $this->assertColumn($column);
        $st = $this->pdo->prepare(
            "UPDATE `{$this->table}` SET `{$column}` = `{$column}` + :step WHERE `id` = :id"
        );
        $st->execute([':step' => $step, ':id' => $id]);
        return $st->rowCount();
    }

    // ---------------------------------------------------------------
    // 內部：白名單與 SQL 組裝
    // ---------------------------------------------------------------

    private function assertColumn(string $column): void
    {
        if (!in_array($column, $this->columns, true)) {
            throw new InvalidArgumentException(
                "illegal column `{$column}` for table `{$this->table}`"
            );
        }
    }

    /** 丟掉不屬於本表的欄位（例如 _token、id） */
    private function filterWritable(array $data): array
    {
        $allowed = self::SCHEMA[$this->table];
        return array_intersect_key($data, array_flip($allowed));
    }

    /** ['a'=>1] → [':a'=>1] */
    private function bindable(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[':' . $k] = $v;
        }
        return $out;
    }

    /**
     * @return array{0:string,1:array} [WHERE 子句, 繫結參數]
     */
    private function buildWhere(array $cond): array
    {
        if ($cond === []) {
            return ['', []];
        }

        $parts = [];
        $bind  = [];
        foreach ($cond as $col => $val) {
            $col = (string) $col;
            $this->assertColumn($col);          // ← 欄位名必過白名單
            $parts[]              = "`{$col}` = :w_{$col}";
            $bind[':w_' . $col]   = $val;       // ← 欄位值必經參數繫結
        }

        return [' WHERE ' . implode(' AND ', $parts), $bind];
    }

    private function buildOrderLimit(array $opt): string
    {
        $sql = '';

        if (!empty($opt['order'])) {
            $col = (string) $opt['order'];
            $this->assertColumn($col);
            $dir = strtoupper((string) ($opt['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$col}` {$dir}";
        }

        if (isset($opt['limit'])) {
            // (int) 轉型後的值不可能包含 SQL 語法，可安全內插
            $sql .= ' LIMIT ' . max(0, (int) $opt['limit']);
            if (isset($opt['offset'])) {
                $sql .= ' OFFSET ' . max(0, (int) $opt['offset']);
            }
        }

        return $sql;
    }
}

// ===================================================================
// 全站共用的資料表物件
// ===================================================================

$Title  = new DB('title');
$Ad     = new DB('ad');
$Mvim   = new DB('mvim');
$Image  = new DB('image');
$News   = new DB('news');
$Admin  = new DB('admin');
$Menu   = new DB('menu');
$Total  = new DB('total');
$Bottom = new DB('bottom');

/**
 * 依資料表名稱取得對應的 DB 物件。
 *
 * 取代舊版的 $db = ${ucfirst($table)}（可變變數）——
 * 舊寫法讓使用者能以 ?table= 指向任意全域變數，也能操作任意資料表，
 * 正是「未登入即可新增管理員帳號」得以成立的機制。
 *
 * admin 表刻意排除在外，只能由 api/admin_*.php 這類專屬端點處理。
 */
function table(string $name, bool $allowAdmin = false): DB
{
    /** @var array<string,DB> $registry */
    static $registry = null;

    if ($registry === null) {
        global $Title, $Ad, $Mvim, $Image, $News, $Menu, $Total, $Bottom;
        $registry = [
            'title'  => $Title,
            'ad'     => $Ad,
            'mvim'   => $Mvim,
            'image'  => $Image,
            'news'   => $News,
            'menu'   => $Menu,
            'total'  => $Total,
            'bottom' => $Bottom,
        ];
    }

    if ($allowAdmin && $name === 'admin') {
        global $Admin;
        return $Admin;
    }

    if (!isset($registry[$name])) {
        app_log('table.illegal_name', ['table' => $name]);
        app_fail(400, '不存在的資料表。');
    }

    return $registry[$name];
}

// ===================================================================
// 進站人數計數
// ===================================================================
// 舊版為「find → $visit['total']++ → save」的讀改寫，有兩個問題：
//   1. 併發時會少算（競態）
//   2. total 資料表若為空，find() 回傳 false，對 false 取索引會讓全站崩潰
// 改為交給資料庫做原子遞增。
//
// 註：以 session 判斷「新訪客」本身仍可被清除 cookie 繞過（A06-4），
//     該部分屬第 2 階段待辦，本次未處理。
if (!isset($_SESSION['visit'])) {
    $_SESSION['visit'] = 1;
    try {
        $Total->increment(1, 'total');
    } catch (PDOException $e) {
        app_log('total.increment_failed', ['error' => $e->getMessage()]);
    }
}

/** 除錯用輔助函式（僅在 dev 環境輸出） */
function dd(mixed $value): void
{
    if (app_config()['env'] === 'prod') {
        return;
    }
    echo '<pre>';
    print_r($value);
    echo '</pre>';
}

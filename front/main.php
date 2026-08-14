                <div class="di" style="height:540px; border:#999 1px solid; width:53.2%; margin:2px 0px 0px 0px; float:left; position:relative; left:20px;">
<?php include "include/marquee.php";?>
                    <div style="height:32px; display:block;"></div>
                    <!--正中央-->

                    <div style="width:100%; padding:2px; height:290px;">
                        <div id="mwww" loop="true" style="width:100%; height:100%;">
                            
                            <div style="width:99%; height:100%; position:relative;" class="cent">沒有資料</div>
                        </div>
                    </div>
                        <script>
                        <?php
                            // 舊版是 echo "lin.push('upload/{$mv['img']}')"，把資料庫的檔名
                            // 直接拼進 JavaScript 字串常值（對應 A05-5）。上傳檔名現已由
                            // 伺服器產生，但資料庫中可能還留有舊的、含引號的檔名，
                            // 因此改用 json_encode 一次輸出整個陣列。
                            $mvs = array_map(
                                static fn(array $mv): string => 'upload/' . $mv['img'],
                                $Mvim->all(['sh'=>1])
                            );
                        ?>
                        var lin = <?= json_encode(
                            $mvs,
                            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                        ) ?>;
                         var now = 0;
                        if (lin.length > 1) {
                            setInterval("ww()", 3000);
                            //now = 1;
                        }
                        
                        function ww() {
                            $("#mwww").html("<embed loop=true src='" + lin[now] + "' style='width:99%; height:100%;'></embed>")
                            //$("#mwww").attr("src",lin[now])
                            now++;
                            if (now >= lin.length)
                                now = 0;
                        }

                        ww();
                    </script>
                    <div style="width:95%; padding:2px; height:190px; margin-top:10px; padding:5px 10px 5px 10px; border:#0C3 dashed 3px; position:relative;">
                        <span class="t botli">最新消息區
                            <a href="?do=news" style="float:right">
                                <?php 
                                if($News->count(['sh'=>1])>5){
                                    echo "More...";
                                }
                                ?>
                            </a>
                        </span>
                        <ul class="ssaa" style="list-style-type:decimal;">
                            <?php 
                            $news=$News->all(['sh'=>1], ['limit'=>5]);
                            foreach($news as $n):
                            ?>
                            <li><?= mb_substr($n['text'],0,25); ?>
                            <div class="all" style="display:none"><?= $n['text']; ?></div>
                            </li>
                            <?php endforeach;?>
                        </ul>
                        <div id="altt" style="position: absolute; width: 350px; min-height: 100px; background-color: rgb(255, 255, 204); top: 50px; left: 130px; z-index: 99; display: none; padding: 5px; border: 3px double rgb(255, 153, 0); background-position: initial initial; background-repeat: initial initial;">
                        </div>
                        <script>
                            $(".ssaa li").hover(
                                function() {
                                    $("#altt").html("<pre>" + $(this).children(".all").html() + "</pre>")
                                    $("#altt").show()
                                }
                            )
                            $(".ssaa li").mouseout(
                                function() {
                                    $("#altt").hide()
                                }
                            )
                        </script>
                    </div>
                </div>
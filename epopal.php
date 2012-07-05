<?php
require_once dirname(__FILE__) . '/inc.php';
$url = optional_param('url', 0, PARAM_URL);
$url=str_replace("http://","",$url); //sicherheit, nur interne links
//echo $url;die;
if ((!isloggedin() || isguestuser()) && $_GET["url"]!=""){
	//redirect(get_login_url());
	$formsub=true;
}else{
	$formsub=false;
	if($url!=""){
		redirect($CFG->wwwroot.$url);
	}
}
?>
<html>
	<head>
		
	</head>
	<body>
<?php


if ($formsub)
{
	$SESSION->wantsurl=$CFG->wwwroot.$url;
	
?>
<div style="visibility:hidden">
<form id="login" name="form" action="<?php echo get_login_url()?>" method="post">
	<ul>
	<!--<li><input id="login_username" class="loginform" type="text"  value="visitor" name="username"></li>
	<li><input id="login_password" class="loginform" type="password"  value="Visitor123!" name="password"></li>-->
	<li><input id="login_username" class="loginform" type="text"  value="visitor" name="username"></li>
	<li><input id="login_password" class="loginform" type="password"  value="Visitor123!" name="password"></li>
	<li>
	<input type="submit" value="Login">
	</li>
	</ul>
</form>
</div>
<script type="text/javascript">
	document.form.submit();
</script>
<?php
}

?>
</body>

</html>
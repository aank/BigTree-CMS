<?
	$user = $admin->getUserById($f["user"]);
?>
<div class="form_container">
	<header><h2><strong>Warning:</strong> This page is currently locked.</h2></header>
	<section>
		<p>
			<strong><?=$user["name"]?></strong> currently has this page locked for editing.  It was last accessed by <strong><?=$user["name"]?></strong> on <strong><?=date("F j, Y @ g:ia",strtotime($f["last_accessed"]))?></strong>.<br />
		If you would like to edit this page anyway, please click "Unlock" below.  Otherwise, click "Cancel".
		</p>
	</section>
	<footer>
		<a href="?force=true" class="button blue">Unlock</a>
		&nbsp;
		<a href="javascript:history.go(-1);" class="button white">Cancel</a>
	</footer>
</div>
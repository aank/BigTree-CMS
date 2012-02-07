<?
	$error = false;
	if (count($_POST)) {
		$f = $admin->getUser($admin->ID);
		$phpass = new PasswordHash($config["password_depth"], TRUE);
		$ok = $phpass->CheckPassword($_POST["current_password"],$f["password"]);
		if ($ok) {
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($_POST["new_password"]));
			sqlquery("UPDATE bigtree_users SET password = '$password' WHERE id = '".$admin->ID."'");
			$admin->growl("Users","Updated Password");
			header("Location: $aroot");
			die();
		}
		$error = true;
	}
	if (!count($_POST) || $error) {
?>
<h2>Change Password</h2>
<p>If you wish to change your password, please enter your current password below and then enter your new password twice.</p>
<br />
<form method="post" action="" class="module">
	<fieldset>
		<label>Current Password</label>
		<input type="password" name="current_password" style="width: 212px;" />
	</fieldset>
	<? if ($error) { ?>
	<div class="error">
		<p>The current password you entered was incorrect.  Please try again.</p>
	</div>
	<? } ?>
	<fieldset class="split">
		<label>New Password</label>
		<input type="password" name="new_password" />
	</fieldset>
	<fieldset class="split second">
		<label>Confirm New Password</label>
		<input type="password" name="confirm_new_password" />
	</fieldset>
	
	<br class="clear" />
	
	<input type="submit" class="button white" value="Update" />
</form>
<script type="text/javascript">
	$("form.module").submit(function() {
		BigTree.ClearFieldAlerts();
		newp = $("input[name=new_password]");
		newpc = $("input[name=confirm_new_password]");
		if (!newp.val()) {
			BigTree.FieldAlert(newp,"You must enter a password.");
			return false;
		} else if (newp.val() != newpc.val()) {
			BigTree.FieldAlert(newpc,"Your passwords don't match.");
			return false;
		}
	});
</script>
<?
	}
?>
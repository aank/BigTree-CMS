<?
	$count = $_POST["count"];
	$items = array();
	$q = sqlquery("SELECT * FROM bigtree_callouts ORDER BY position DESC, id ASC");
	while ($f = sqlfetch($q)) {
		$items[] = $f;
	}
	
	$type = $items[0]["id"];
	
	$callout = json_decode(base64_decode($_POST["data"]),true);
?>
<div id="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<select name="callouts[<?=$count?>][type]">
			<? foreach ($items as $item) { ?>
			<option value="<?=htmlspecialchars($item["id"])?>"<? if ($item["id"] == $callout["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($item["name"])?></option>
			<? } ?>
		</select>
	</fieldset>
</div>
<div id="callout_resources">
	<? include bigtree_path("admin/ajax/pages/callout-resources.php") ?>
</div>

<script type="text/javascript">
	BigTreeCustomControls();
	
	$("#callout_type select").bind("select:changed",function(event,data) {
		$("#callout_resources").load("<?=$admin_root?>ajax/pages/callout-resources/", { type: data.value, count: <?=$count?>, resources: "<?=$_POST["data"]?>" });
	});
</script>
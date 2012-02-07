<?
	$callouts = $admin->getCallouts();
	
	// Need to create a ridiculous hack because jQuery's sortable is retarded.
	$x = 0;
	$rel_table = array();
?>
<h1><span class="icon_developer_callouts"></span>Callouts</h1>
<? include bigtree_path("admin/modules/developer/callouts/_nav.php") ?>

<div class="table">
	<summary><h2>Callouts</h2></summary>
	<header>
		<span class="developer_templates_name">Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="callouts">
		<?
			foreach ($callouts as $callout) {
				$x++;
				$rel_table[$x] = $callout["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<?=$callout["name"]?>
			</section>
			<section class="view_action">
				<a href="<?=$sroot?>edit/<?=$callout["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$sroot?>delete/<?=$callout["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script type="text/javascript">
	$("#callouts").sortable({ items: "li", handle: ".icon_sort", update: function() {
		$.ajax("<?=$aroot?>ajax/developer/order-callouts/?sort=" + escape($("#callouts").sortable("serialize")), { type: "POST", data: { rel: <?=json_encode($rel_table)?> }});
	}});
	
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Callout",'<p class="confirm">Are you sure you want to delete this callout?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>
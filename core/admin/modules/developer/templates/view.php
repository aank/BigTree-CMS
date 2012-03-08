<?
	$templates = $admin->getTemplates();
	
	// Need to create a ridiculous hack because jQuery's sortable is retarded.
	$x = 0;
	$rel_table = array();
?>
<h1><span class="icon_developer_templates"></span>Templates</h1>
<? include bigtree_path("admin/modules/developer/templates/_nav.php") ?>
<div class="table">
	<summary><h2>Basic Templates</h2></summary>
	<header>
		<span class="developer_templates_name">Template Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="basic_templates">
		<?
			foreach ($templates as $template) {
				if (!$template["routed"]) {
					$x++;
					$rel_table[$x] = $template["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<?=$template["name"]?>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$template["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$template["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<div class="table">
	<summary><h2>Routed Templates</h2></summary>
	<header>
		<span class="developer_templates_name">Template Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="routed_templates">
		<?
			foreach ($templates as $template) {
				if ($template["routed"]) {
					$x++;
					$rel_table[$x] = $template["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<?=$template["name"]?>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$template["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$template["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<script type="text/javascript">
	$("#basic_templates").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/developer/order-templates/?sort=" + escape($("#basic_templates").sortable("serialize")), { type: "POST", data: { rel: <?=json_encode($rel_table)?> } }); 
	}});
	
	$("#routed_templates").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/developer/order-templates/?sort=" + escape($("#routed_templates").sortable("serialize")), { type: "POST", data: { rel: <?=json_encode($rel_table)?> } }); 
	}});
	
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Template",'<p class="confirm">Are you sure you want to delete this template?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>
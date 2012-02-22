<div class="form_container">
	<header>
		<div class="sticky_controls">
			<div class="shadow">
				<nav class="left">
					<a href="#properties_tab"<? if ($action == "create") { ?> class="active"<? } ?>>Properties</a>
					<a href="#template_tab">Template</a>
					<a href="#content_tab"<? if ($action == "update") { ?> class="active"<? } ?>>Content</a>
					<a href="#seo_tab">SEO</a>
					<? if ($admin->Level > 0 && $action == "update") { ?>
					<a href="#move_tab">Move</a>
					<? } ?>
				</nav>
				<div id="link_finder_results" style="display: none;"></div>
				<input type="search" id="link_finder" class="form_search" autocomplete="off" placeholder="Link Finder" />
			</div>
		</div>
		<script type="text/javascript">
			$(".form_container nav a").click(function() {
				if ($(this).hasClass("active")) {
					return;
				}
				
				t = $(".form_container").offset().top;
				if (window.scrollY > t) {
					$('html, body').animate({
						scrollTop: $(".form_container").offset().top
					}, 200);
				}
				
				href = $(this).attr("href").substr(1);
				$(".form_container > form > section").hide();
				$(".form_container nav a").removeClass("active");
				$(this).addClass("active");
				$("#" + href).show();
				
				// Manage the "Next" buttons
				nav = $(".form_container nav a");
				index = nav.index(this);
				if (index == nav.length - 1) {
					$(".next").hide();
				} else {
					$(".next").show();				
				}
				
				return false;
			});
		</script>
	</header>
	<? include bigtree_path("admin/layouts/_tinymce.php"); ?>
	<form method="post" class="module" action="<?=$aroot?>pages/<?=$action?>/" enctype="multipart/form-data" id="page_form">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=upload_max_filesize()?>" />
		
		<? if ($pdata) { ?>
		<input type="hidden" name="page" value="<?=$pdata["id"]?>" />
		<? } else { ?>
		<input type="hidden" name="parent" value="<?=$parent?>" />
		<? } ?>
		
		<section id="properties_tab"<? if ($action == "update") { ?> style="display: none;"<? } ?>>
			<? include bigtree_path("admin/modules/pages/tabs/properties.php") ?>
		</section>
		<section id="template_tab" style="display: none;">
			<? include bigtree_path("admin/modules/pages/tabs/template.php") ?>
		</section>
		<section id="content_tab"<? if ($action == "create") { ?> style="display: none;"<? } ?>>
			<? include bigtree_path("admin/modules/pages/tabs/content.php") ?>
		</section>
		<section id="seo_tab" style="display: none;">
			<? include bigtree_path("admin/modules/pages/tabs/seo.php") ?>
		</section>
		<? if ($admin->Level > 0 && $action == "update") { ?>
		<section id="move_tab" style="display: none;">
			<? include bigtree_path("admin/modules/pages/tabs/move.php") ?>
		</section>
		<? } ?>
		<footer>
			<a href="#" class="next button">Next Step &raquo;</a>

			<?
				if ($action == "create") {
			?>
			<input type="submit" name="ptype" value="Create" <? if (!$publisher) { ?>class="blue" <? } ?>/>
			<?
					if ($publisher) {
			?>
			<input type="submit" name="ptype" value="Create &amp; Publish" class="blue" />
			<?
					}
				} else {
			?>
			<a href="#" class="button save_and_preview"><span></span>Save &amp; Preview</a>
			<input type="submit" name="ptype" value="Save"<? if (!$publisher) { ?> class="blue"<? } ?> />
			<?
					if ($publisher) {
			?>
			<input type="submit" name="ptype" value="Save &amp; Publish" class="blue" />
			<?
					}
				}
			?>
		</footer>
	</form>
</div>

<script type="text/javascript">
	var template = "<?=$default_template?>";
	<? if ($action == "create") { ?>
	var page = false;
	<? } else { ?>
	var page = "<?=$pdata["id"]?>";
	var page_updated_at = "<?=$pdata["updated_at"]?>";
	lockTimer = setInterval("$.ajax('<?=$aroot?>ajax/pages/refresh-lock/', { type: 'POST', data: { id: '<?=$lockid?>' } });",60000);
	//seoTimer = setInterval("updateSEOScore();",5000);
	<? } ?>
	
	new BigTreeFormValidator("#page_form");
</script>
<script type="text/javascript" src="<?=$aroot?>js/pages.js"></script>
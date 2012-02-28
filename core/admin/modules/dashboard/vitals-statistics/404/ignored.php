<?
	$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored != ''"));
	$total = $total["total"];
	$type = "ignored";
	$breadcrumb[] = array("link" => "dashboard/404/ignored/", "title" => "Ignored 404s");
	$delete_action = "unignore";
?>
<h1><span class="page_404"></span>Ignored 404s</h1>
<? include bigtree_path("admin/modules/dashboard/vitals-statistics/404/_nav.php") ?>
<div class="table">
	<summary class="taller">
		<input type="search" class="form_search" placeholder="Search" id="404_search" />
		<p><?=$total?> URL<? if ($total != 1) { ?>s<? } ?> have been ignored from the main list. &mdash; Redirect URLs save automatically as you type them.</p>
	</summary>
	<header>
		<span class="requests_404">Requests</span>
		<span class="url_404">404 URL</span>
		<span class="redirect_404">Redirect</span>
		<span class="ignore_404">Unignore</span>
	</header>
	<ul id="results">
		<? include bigtree_path("admin/ajax/dashboard/404/search.php") ?>
	</ul>
</div>
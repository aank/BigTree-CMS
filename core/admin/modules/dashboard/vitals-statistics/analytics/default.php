<?
	include bigtree_path($relative_path."_check.php");
	
	$breadcrumb[] = array("link" => "dashboard/analytics/", "title" => "Traffic Report");
	
	$cache = $cms->getSetting("bigtree-internal-google-analytics-cache");
	
	$two_week_visits = $cache["two_week"];
	$graph_min = min($two_week_visits);
	$graph_max = max($two_week_visits) - $graph_min;
	$graph_bar_height = 70;
	
	// Get the beginning month of the current quarter
	$current_quarter_month = date("m") - date("m") % 3 + 1;
	
	function _local_compareData($current,$past) {
		if ($past["views"]) {
			$view_growth = number_format((($current["views"]-$past["views"]) / $past["views"]) * 100,2)."%";
		} else {
			$view_growth = "N/A";
		}
		
		if ($past["visits"]) {
			$visits_growth = number_format((($current["visits"]-$past["visits"]) / $past["visits"]) * 100,2)."%";
		} else {
			$visits_growth = "N/A";
		}
		
		if ($past["bounce_rate"]) {
			$bounce_growth = number_format($current["bounce_rate"]-$past["bounce_rate"],2)."%";
		} else {
			$bounce_growth = "N/A";
		}
		
		if ($past["average_time_seconds"]) {
			$time_growth = number_format((($current["average_time_seconds"]-$past["average_time_seconds"]) / $past["average_time_seconds"]) * 100,2)."%";
		} else {
			$time_growth = "N/A";
		}
		
		$c_min = "";
		$c_seconds = floor($current["average_time_seconds"])." second(s)";
		$c_time = $current["average_time_seconds"];
		if ($c_time > 60) {
			$c_minutes = floor($c_time / 60);
			$c_seconds = floor($c_time - ($c_minutes * 60))." second(s)";
			$c_min = $c_minutes." minute(s)";
		}
		$c_time = trim($c_min." ".$c_seconds);
		
		$p_ = "";
		$p_seconds = floor($past["average_time_seconds"])." second(s)";
		$p_time = $past["average_time_seconds"];
		if ($p_time > 60) {
			$p_minutes = floor($p_time / 60);
			$p_seconds = floor($p_time - ($p_minutes * 60))." second(s)";
			$p_min = $p_minutes." minute(s)";
		}
		$p_time = trim($p_min." ".$p_seconds);
		
		if ($view_growth > 5) {
			$view_class = 'growth';
		} elseif ($view_growth < -5) {
			$view_class = 'warning';
		}
		
		if ($visits_growth > 5) {
			$visit_class = 'growth';
		} elseif ($visits_growth < -5) {
			$visit_class = 'warning';
		}
		
		if ($time_growth > 5) {
			$time_class = "growth";
		} elseif ($time_growth < -5) {
			$time_class = "warning";
		}
		
		if ($bounce_growth < -2) {
			$bounce_class = 'growth';
		} elseif ($bounce_growth > 2) {
			$bounce_class = 'warning';
		}
?>
<div class="set">
	<div class="data">
		<header>Views<small>Growth</small></header>
		<p class="percentage <?=$view_class?>"><?=$view_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["views"])?></p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["views"])?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header>Visits<small>Growth</small></header>
		<p class="percentage <?=$visit_class?>"><?=$visits_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["visits"])?></p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["visits"])?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header>Average Time on Site<small>Growth</small></header>
		<p class="percentage <?=$time_class?>"><?=$time_growth?></p>
		<label>Present</label>
		<p class="value"><?=$c_time?></p>
		<label>Year-ago</label>
		<p class="value"><?=$p_time?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header>Bounce Rate<small>Growth</small></header>
		<p class="percentage <?=$bounce_class?>"><?=$bounce_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["bounce_rate"],2)?>%</p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["bounce_rate"],2)?>%</p>
	</div>
</div>
<?
	}
?>
<h1>
	<span class="analytics"></span>Traffic Report
	<? include bigtree_path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<? include bigtree_path($relative_path."_nav.php"); ?>
<div class="table">
	<summary>
		<h2>Two Week Heads-Up <small>(visits)</small></h2>
	</summary>
	<section>
		<div class="graph">
			<?
				$x = 0;
			    foreach ($two_week_visits as $date => $count) {
			    	$height = round($graph_bar_height * ($count - $graph_min) / $graph_max) + 12;
			    	$x++;
			?>
			<section class="bar<? if ($x == 14) { ?> last<? } elseif ($x == 1) { ?> first<? } ?>" style="height: <?=$height?>px; margin-top: <?=(82-$height)?>px;">
			    <?=$count?>
			</section>
			<?
			    }
			    
			    $x = 0;
			    foreach ($two_week_visits as $date => $count) {
			    	$x++;
			?>
			<section class="date<? if ($x == 14) { ?> last<? } elseif ($x == 1) { ?> first<? } ?>"><?=date("n/j/y",strtotime($date))?></section>
			<?
			    }
			?>
		</div>
	</section>
</div>

<ul class="analytics_columns">
	<li>
		<summary>Current Month <small>(<?=date("n/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["month"],$cache["year_ago_month"]); ?>
	</li>
	<li>
		<summary>Current Quarter <small>(<?=date("$current_quarter_month/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["quarter"],$cache["year_ago_quarter"]); ?>
	</li>
	<li>
		<summary>Current Year <small>(<?=date("1/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["year"],$cache["year_ago_year"]); ?>
	</li>
</ul>

<script type="text/javascript">
	$("#graph_data").load("<?=$admin_root?>ajax/analytics/get-graph/", { start_date: "<?=$tw_start?>", end_date: "<?=$tw_end?>" });
	$("#ga_current_month").load("<?=$admin_root?>ajax/analytics/get-date-range/", { title: "Current Month", start_date: "<?=date("Y-m-01")?>", end_date: "<?=date("Y-m-d")?>" });
	$("#ga_current_quarter").load("<?=$admin_root?>ajax/analytics/get-date-range/", { title: "Current Quarter", start_date: "<?=$quarter_start?>", end_date: "<?=$quarter_end?>" });
	$("#ga_current_year").load("<?=$admin_root?>ajax/analytics/get-date-range/", { title: "Current Year", start_date: "<?=$year_start?>", end_date: "<?=$year_end?>" });
</script>
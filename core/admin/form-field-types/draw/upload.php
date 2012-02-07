<?	
	if (!$options["image"]) {
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<input<?=$input_validation_class?> type="file" tabindex="<?=$tabindex?>" name="<?=$key?>" id="field_<?=$key?>" />
	<? if ($value) { ?>
	<input type="hidden" name="<?=$currently_key?>" value="<?=htmlspecialchars($value)?>" id="field_<?=$key?>" />
	<div>
		<small>Currently: <?=$value?> <a href="#" class="remove_resource">Remove</a></small>	
	</div>
	<? } ?>
</fieldset>
<?
	} else {
		if ($options["preview_prefix"]) {
			$preview_image = file_prefix($value,$options["preview_prefix"]);
		} else {
			$preview_image = $value;
		}
		
		$button_options = array(
			"minWidth" => $options["min_width"],
			"minHeight" => $options["min_height"],
			"inputField" => $currently_key,
			"currentlyKey" => $currently_key,
			"previewPrefix" => $options["preview_prefix"]
		);
		if ($value) {
			$buttion_options["preview"] = true;
		}
		$button_options = htmlspecialchars(json_encode($button_options));
?>
<fieldset class="image_field">
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<input<?=$input_validation_class?> type="file" tabindex="<?=$tabindex?>" name="<?=$key?>" id="field_<?=$key?>" />
	<? if (!$no_file_browser) { ?>
	<span class="or">OR</span>
	<a href="#field_currently_<?=$key?>" name="<?=$button_options?>" class="button form_image_browser"><span class="icon_images_small"></span>Browse</a>
	<? } ?>
	<br class="clear" />
	<div class="currently" id="field_currently_<?=$key?>"<? if (!$value) { ?> style="display: none;"<? } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<img src="<?=$preview_image?>" alt="" />
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$currently_key?>" value="<?=htmlspecialchars($value)?>" />
	</div>
</fieldset>
<?
	}
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($d["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Directory <small>(relative to site root, i.e. &ldquo;images/features/&rdquo;)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($d["directory"])?>" />
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($d["prefix"])?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<select name="image">
		<? bigtree_field_select($table,$d["image"]) ?>
	</select>
</fieldset>

<fieldset>
	<label>Caption Field</label>
	<select name="caption">
		<? bigtree_field_select($table,$d["caption"]) ?>
	</select>
</fieldset>
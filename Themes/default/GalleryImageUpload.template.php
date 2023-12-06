<?php

/**
 * @package Gallery Post Image Upload
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2023, SMF Tricks
 * @license MIT
 */

function template_galleryimageupload_above()
{
	global $context, $settings;

	// Since this is a popup of its own we need to start the html, etc.
	echo '
	<!DOCTYPE html>
	<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
		<head>
			<meta charset="', $context['character_set'], '">
			<meta name="robots" content="noindex">
			<title>', $context['page_title'], '</title>
			<script src="', $settings['default_theme_url'], '/scripts/galleryimageupload.js"></script>
		</head>
		<body id="gallery_image_upload">
			<div class="windowbg">';
}

function template_galleryimageupload_below()
{
	global $txt;

	echo '
				<script>
					gallery_form_controls();
				</script>

				<br class="clear">
				<a href="javascript:self.close();" style="display: none">', $txt['close_window'], '</a>
			</div><!-- .windowbg -->
		</body>
	</html>';
}

function template_galleryimageupload()
{
	global $context, $scripturl, $txt;

	// Error?
	if (!empty($context['gallery_error']))
	{
		echo '
			<div class="errorbox">', $context['gallery_error'], '</div>';

		return;
	}

	echo '
		<form method="post" enctype="multipart/form-data" name="pictureForm" id="pictureForm" action="', $scripturl, '?action=gallery_upload;save" target="_self">
	
			<fieldset style="padding-block: 0.2em; display: grid;">
				<legend>', $txt['gallery_form_addpicture'], '</legend>';

		// Gallery categories
		if (!empty($context['gallery_cat']))
		{
			echo '
				<label>
					<input id="selectedGallery" type="radio" name="upload_location" value="main" required', (empty($context['user_categories']) ? ' checked' : ''), '>
						', $txt['smfgallery_menu'], '
				</label>';
		}

		// User categories
		if (!empty($context['user_categories']) && !empty($context['gallery_ispro']))
		{
			echo '
				<label>
					<input id="selectedUser" type="radio" name="upload_location" value="user" required', (empty($context['gallery_cat']) ? ' checked' : ''), '>
					', $txt['gallery_user_title2'], '
				</label>';
		}

		echo '
			</fieldset>

			<dl class="settings">
				<dt>
					<label for="gallery_title">', $txt['gallery_form_title'], '</label>
				</dt>
				<dd>
					<input name="title" id="gallery_title" type="text" size="50" autocomplete="off">
				</dd>

				<dt>
					<label for="gallery_cat">', $txt['gallery_form_category'], '</label>
				</dt>
				<dd>
					<select name="cat" id="gallery_cat" required>
						<option value="">', $txt['gallery_text_choose_cat'], '</option>';

		// Main Categories
		if (!empty($context['gallery_cat']))
		{
			echo '
						<optgroup id="galleryGroup" label="', $txt['smfgallery_menu'], '">';
		
			foreach ($context['gallery_cat'] as $category)
			{
				echo '
							<option value="', $category['id_cat'], '">', $category['title'], '</option>';
			}
		
			echo '
						</optgroup>';
		}

		// User Categories
		if (!empty($context['user_categories']) && !empty($context['gallery_ispro']))
		{
			echo '
						<optgroup id="userGroup" label="', $txt['gallery_user_title2'], '">';

			foreach ($context['user_categories'] as $category)
			{
				echo '
							<option value="', $category['user_id_cat'], '">', $category['title'], '</option>';
			}

			echo '
						</optgroup>';
		}

		echo '
					</select>
				</dd>

				<dt>
					<label for="gallery_file">', $txt['gallery_form_title'], '</label>
				</dt>
				<dd>
					<input name="picture" id="gallery_file" type="file" accept="image/gif, image/jpeg, image/jpg, image/png, video/mp4" required>
				</dd>
			</dl>

			<button class="button floatright" id="uploadPicture" type="submit">', $txt['gallery_form_addpicture'], '</button>';

	// Additional Options
	if (!empty($context['gallery_ispro']))
	{
		echo '
			<br>
			<hr>
			<details id="gallery_additionaloptions">
				<summary style="cursor: pointer; user-select: none; padding-inline:0.75em;">
					<strong>', $txt['post_additionalopt'], '</strong>
				</summary>

				<dl class="settings" style="margin-block: 0;">
					<dt>
						<label for="gallery_desc">', $txt['gallery_form_description'], '</label>
					</dt>
					<dd>
						<textarea style="min-height: unset;" name="descript" id="gallery_desc" rows="2"></textarea>
					</dd>

					<dt>
						<label for="gallery_keywords">', $txt['gallery_form_keywords'], '</label>
					</dt>
					<dd>
						<input name="keywords" id="gallery_keywords" type="text" size="50" autocomplete="off">
					</dd>

					<dt>
						<label for="gallery_rotate">', $txt['gallery_text_rotate_image'], '</label>
					</dt>
					<dd>
						<input name="degrees" id="gallery_rotate" type="number" min="0" max="360" value="0">
					</dd>
				</dl>';

		// Custom Fields
		if (!empty($context['gallery_cat']) && !empty($context['gallery_cfields']))
		{
			echo '
				<dl class="settings" style="display: none;" id="gallery_custom_fields">';

			// Display the fields
			foreach ($context['gallery_cfields'] as $cf_id => $field)
			{
				echo '
					<dt id="dt_cf_', $cf_id, '">
						<label for="custom_field[', $cf_id, ']">', $field['title'], '</label>
					</dt>
					<dd id="dd_cf_', $cf_id, '">
						<input id="custom_field[', $cf_id, ']"
							data-category="', $field['id_cat'], '"
							data-customfield="', $cf_id, '"
							name="custom_field[', $cf_id, ']"', (!empty($field['default']) ? ' 
							value="' . $field['default'] . '"' : ''), (!empty($field['is_required']) ? ' 
							data-required="1"' : ''), '>
					</dd>';
			}

			echo '
				</dl>';
		}

		echo '
			</details>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}
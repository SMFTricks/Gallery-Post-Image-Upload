/**
 * @package Gallery Post Image Upload
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2023, SMF Tricks
 * @license MIT
 */

function gallery_insert_text()
{
	// Posting
	let posting_view = document.getElementById('post_additional_options_header');
	// Post
	let post_view = document.getElementById('post_confirm_buttons');
	let post_view_expanded = document.getElementById('post_additional_options');
	// Default
	let inserting_where = posting_view ?? (post_view_expanded ?? post_view);

	// Elements
	let add_picture_block = document.createElement('div');
	let add_picture = document.createElement('a');
	let add_icon = document.createElement('span');

	// Icon
	add_icon.className = 'main_icons attachment';

	// Block
	add_picture_block.style.cssText = 'font-size: 1.1em; font-weight: 700; padding: 0.5em 0.75em; display: flex; gap: 0.75em;';
	add_picture_block.className = 'roundframe';
	add_picture_block.id = 'smf_gallery_image_upload';
	add_picture_block.appendChild(add_icon);
	add_picture_block.appendChild(add_picture);
	// Add the text
	add_picture.innerText = smf_gallery_image_post;
	// Add the URL
	add_picture.setAttribute('onclick', 'return reqOverlayDiv(\'' + smf_scripturl + '?action=gallery_upload\', \'' + smf_gallery_picture_add + '\', \'attachment\');');

	// Insert the text
	inserting_where.parentNode.insertBefore(add_picture_block, inserting_where)
}

function gallery_form_controls()
{
	// Disable click for popup
	document.getElementById('smf_popup')?.addEventListener('mouseup', e =>  {
		e.stopPropagation();
	});

	// Form
	document.getElementById('pictureForm')?.addEventListener('submit', e => {
		// Don't leave the page
		e.preventDefault();

		// Upload
		gallery_uploadimage();
	});

	// Radio and select
	let category_select = document.getElementById('gallery_cat');
	// Selecting with radio
	document.getElementById('selectedGallery')?.addEventListener('change', e => {
		category_select.value = '';
		if (e.target.checked)
		{
			gallery_checkRadio('selectedGallery');
		}
	});
	document.getElementById('selectedUser')?.addEventListener('change', e => {
		category_select.value = '';
		if (e.target.checked)
		{
			gallery_checkRadio('selectedUser');
		}
	});
	// Select
	category_select?.addEventListener('change', function(){
		if (this.selectedOptions[0].parentElement.id == 'galleryGroup')
		{
			document.getElementById('selectedGallery')?.setAttribute('checked', '');
			document.getElementById('selectedUser')?.removeAttribute('checked');
			gallery_checkRadio('selectedGallery', this.selectedOptions[0].value);
		}
		if (this.selectedOptions[0].parentElement.id == 'userGroup')
		{
			document.getElementById('selectedUser')?.setAttribute('checked', '');
			document.getElementById('selectedGallery')?.removeAttribute('checked');
			gallery_checkRadio('selectedUser', this.selectedOptions[0].value);
		}
	});
}

function gallery_checkRadio(input, selectedOption)
{
	// Gallery group
	let gallery_group = document.getElementById('galleryGroup');
	// User group
	let user_group = document.getElementById('userGroup');

	// Custom fields
	let custom_fields = document.getElementById('gallery_custom_fields');

	// No groups?
	if (!gallery_group && !user_group)
		return;

	if (input === 'selectedGallery')
	{
		gallery_group.style.display = 'block';

		// User
		if (user_group)
		{
			user_group.style.display = 'none';
		}
		// Custom Fields
		if (custom_fields)
		{
			// Show the custom fields
			custom_fields.style.display = 'block';
			// Details
			let showdetails = false;

			custom_fields.querySelectorAll('input').forEach(e => {
				// Toggle if something is required
				if (e.dataset.required)
				{
					// Toggle details
					showdetails = true;
					// Set required
					e.setAttribute('required', '');
				}
				// Remove those that aren't valid
				if (e.dataset.category !== selectedOption && e.dataset.category != 0)
				{
					document.querySelectorAll('dt#dt_cf_' + e.dataset.customfield + ', dd#dd_cf_' + e.dataset.customfield).forEach(dl => {
						dl.style.display = 'none';
					});
				}
				// Maybe it is a specific category already?
				if (e.dataset.category === selectedOption)
				{
					document.querySelectorAll('dt#dt_cf_' + e.dataset.customfield + ', dd#dd_cf_' + e.dataset.customfield).forEach(dl => {
						dl.style.display = 'block';
					});
				}
			});

			if (showdetails)
			{
				document.getElementById('gallery_additionaloptions').open = true;
			}
		}
	}
	else
	{
		user_group.style.display = 'block';

		// Gallery
		if (gallery_group)
		{
			gallery_group.style.display = 'none';
		}
		// Custom Fields
		if (custom_fields)
		{
			// Hide the custom fields
			custom_fields.style.display = 'none';

			custom_fields.querySelectorAll('input').forEach(e => {
				// None is required now
				e.removeAttribute('required');

				// And all of them are visible now
				document.querySelectorAll('dt#dt_cf_' + e.dataset.customfield + ', dd#dd_cf_' + e.dataset.customfield).forEach(dl => {
					dl.style.display = 'block';
				})			
			});
		}
	}
}

function gallery_uploadimage()
{
	let form = document.getElementById('pictureForm');
	let formData = new FormData(form);
	let xhr = new XMLHttpRequest();

	// Create a loading
	let loading = document.createElement('img');
	loading.src = smf_images_url + '/loading_sm.gif';
	loading.atl = '';
	loading.id = 'sploadingGallery';
	loading.style.cssText = 'margin-block: 5px; margin-inline: auto; display: block;';

	// Create info box
	let infobox = document.createElement('div');
	infobox.style.display = 'none';
	infobox.style.marginBlock = '0';

	// Popup
	let popup = document.getElementById('smf_popup');

	// The picture
	let picture_src = '';

	// Post
	xhr.open('POST', form.action);

	// Start
	xhr.onloadstart = () => {
		// Message
		form.insertAdjacentElement('beforebegin', infobox);
		// Loading
		form.insertAdjacentElement('beforebegin', loading);
		// Disable the button
		form.querySelector('button').setAttribute('disabled', '');
		// Hide the form
		form.style.display = 'none';
	}

	// End
	xhr.onloadend = () => {
		// Hide the loading
		loading.style.display = 'none';

		// Style of message
		switch (xhr.status)
		{
			case 200:
				infobox.className = 'infobox';
				let response = JSON.parse(xhr.responseText);
				infobox.innerText = response.message;
				picture_src = response.picture_src;
				break;
			default:
				infobox.className = 'errorbox';
				infobox.innerText = xhr.responseText;
				break;
		}

		// Display the info box
		infobox.style.display = 'block';

		// Response
		setTimeout(() => {
			// Remove it
			setTimeout(() => {
				popup.remove();
			}, 750);

			// Fade
			popup.style.transition = 'opacity 2s ease-in-out';
			popup.style.opacity = '0';

			// Insert the code
			if (xhr.status === 200 && picture_src)
			{
				gallery_insert_code(picture_src);
			}
		}, 1000);
	};

	// Send
	xhr.send(formData);
}

function gallery_insert_code(picture)
{
	// Is there an image?
	if (!picture)
		return;

	// Posting
	let quick_reply = document.querySelector('#quickreply_options .sceditor-container');
	// Post
	let full_reply = document.querySelector('#post_area .sceditor-container');
	// Default
	let posting_where = quick_reply ?? full_reply;

	// Image
	let image = document.createElement('img');
	image.src = picture;

	// Paragraph
	let parap = document.createElement('p');
	// Insert image
	parap.appendChild(image);

	// wysiwygMode
	if (posting_where.classList.contains('wysiwygMode'))
	{
		insert_where = posting_where.querySelector('iframe').contentDocument.body;
		insert_where.appendChild(parap);
	}
	// Source view
	else
	{
		insert_where = posting_where.querySelector('textarea');
		insert_where.value += '\n[img]' + picture + '[/img]';
		insert_where.focus();
	}
}
<?php

/**
 * @package Gallery Post Image Upload
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2023, SMF Tricks
 * @license MIT
 */

class GalleryImageUpload
{
	/**
	 * Quick Reply Behavior from Enhanced Quick Reply MOD
	 */
	private static bool $_qr_disabled = false;

	/**
	 * Gallery Error
	 */
	private string $_gallery_error = '';

	/**
	 * Gallery pro?
	 */
	private bool $_gallery_pro = false;

	/**
	 * Gallery permissions
	 */
	private array $_gallery_catperm;

	/**
	 * User Group
	 */
	private int $_user_group = 0;

	/**
	 * Custom fields
	 */
	private array $_gallery_cfields;

	/**
	 * Add the custom action
	 * 
	 * @param $actions The actions array
	 */
	public static function action(&$actions) : void
	{
		$actions['gallery_upload'] = ['GalleryImageUpload.php', __CLASS__ . '::upload#'];
	}

	/**
	 * Add the link to uploading
	 */
	public static function loadJavaScript() : void
	{
		global $txt, $context;

		// Quick reply behavior?
		self::qr_behavior();

		// Anything to do?
		if (!allowedTo('smfgallery_add') || (isset($context['can_reply']) && empty($context['can_reply'])) || !empty(self::$_qr_disabled))
			return;

		loadLanguage('Gallery');
		addJavaScriptVar('smf_gallery_image_post', $txt['gallery_txt_imagepost'], true);
		addJavaScriptVar('smf_gallery_picture_add', $txt['gallery_text_addpicture'], true);
		loadJavaScriptFile('galleryimageupload.js', ['default_theme' => true, 'defer' => true], 'galleryimageupload');
		addInlineJavaScript('gallery_insert_text();', true);
	}

	/**
	 * Figure out the current behavior of the quick reply
	 */
	private static function qr_behavior() : void
	{
		global $options, $modSettings;

		// The default and general behavior
		self::$_qr_disabled = isset($modSettings['QuickReply_behavior_general']) && $modSettings['QuickReply_behavior_general'] === 'disabled';

		// Override from the user behavior
		if (allowedTo('QuickReply_behavior'))
			self::$_qr_disabled = isset($options['QuickReply_behavior']) && $options['QuickReply_behavior'] === 'disabled';
	}

	/**
	 * Main function for uploading the item to the gallery
	 */
	public function upload() : void
	{
		global $context, $txt, $sourcedir;

		// Allowed to post to the gallery?
		isAllowedTo('smfgallery_add');

		// Language
		loadLanguage('Gallery');
		loadLanguage('Help');

		// Gallery
		require_once($sourcedir . '/Gallery2.php');

		// Saving?
		if (isset($_REQUEST['save']))
			$this->save();

		loadLanguage('Post');
		loadTemplate('GalleryImageUpload');
		checkSubmitOnce('register');

		// Get the lists
		$this->form();

		$context['page_title'] = $txt['gallery_form_addpicture'];
		$context['gallery_error'] =  $this->_gallery_error;
		$context['gallery_ispro'] = $this->_gallery_pro;
		$context['sub_template'] = 'galleryimageupload';
		$context['template_layers'][] = 'galleryimageupload';
		$context['from_ajax'] = true;

		loadJavaScriptFile('galleryimageupload.js', ['default_theme' => true, 'defer' => true,], 'galleryimageupload');
	}

	/**
	 * Get the information for the form
	 */
	private function form() : void
	{
		global $context, $txt;

		// Pro gallery?
		$this->_gallery_pro = function_exists('CreateGalleryPrettyCategory') ?? false;

		print_r($this->_gallery_pro); 

		// User Gallery
		if (!empty($this->_gallery_pro))
		{
			$this->userGalleries();
		}
		
		// Categories
		$this->forumGalleries();

		// Check if there are categories
		if (empty($context['gallery_cat']) && empty($context['user_categories']))
			$this->_gallery_error = $txt['gallery_error_no_catexists'];
	}

	private function forumGalleries() : void
	{
		global $context, $smcFunc, $user_info;

		// User Group
		// The gallery uses the values backwards for some reason LOL
		$this->_user_group = $user_info['is_guest'] ? -1 : ($user_info['groups'][0] ?? 0);

		// Permissions
		if (!empty($this->_gallery_pro))
		{
			$this->categoriesPermissions();
		}

		// Get the categories
		$query = $smcFunc['db_query']('', '
			SELECT
				c.id_cat, c.title' . (!empty($this->_gallery_pro) ? ', c.locked, c.id_parent' : '') . '
			FROM {db_prefix}gallery_cat AS c
			WHERE c.redirect = {int:redirect}
			ORDER BY c.title ASC',
			[
				'redirect' => 0,
			]
		);

		$context['gallery_cat'] = [];
		while($row = $smcFunc['db_fetch_assoc']($query))
		{
			 // Skip category if it is locked
			if (!allowedTo('smfgallery_manage') && !empty($row['locked']))
				continue;

			// Check if they have permissions
			if (!empty($this->_gallery_catperm) && in_array($row['id_cat'], array_keys($this->_gallery_catperm)))
			{
				// View gallery or add picture
				if (isset($this->_gallery_catperm[$row['id_cat']][$this->_user_group]) && ($this->_gallery_catperm[$row['id_cat']][$this->_user_group]['view'] == 0 || $this->_gallery_catperm[$row['id_cat']][$this->_user_group]['add'] == 0))
					continue;
			}
			$context['gallery_cat'][] = $row;
		}
		$smcFunc['db_free_result']($query);

		// Pro gallery
		if (!empty($this->_gallery_pro))
		{
			// Pretty galleries
			CreateGalleryPrettyCategory();

			// Custom fields
			$this->customFields();
			$context['gallery_cfields'] = $this->_gallery_cfields ?: [];
		}
	}

	private function userGalleries() : void
	{
		global $context, $smcFunc, $user_info;

		if (!allowedTo('smfgallery_usergallery') || !empty($user_info['is_guest']))
			return;

		$query = $smcFunc['db_query']('', '
			SELECT USER_ID_CAT AS user_id_cat, title, roworder, id_parent
			FROM {db_prefix}gallery_usercat
			WHERE id_member = {int:member}
			ORDER BY title ASC',
			[
				'member' => $user_info['id'],
			]
		);

		$context['gallery_cat'] = [];
		while($row = $smcFunc['db_fetch_assoc']($query))
			$context['gallery_cat'][] = $row;
		$smcFunc['db_free_result']($query);

		// Just in case
		if (!empty($this->_gallery_pro))
			CreateUserGalleryPrettyCategory();

		// User array
		$context['user_categories'] = $context['gallery_cat'];
	}

	/**
	 * Actually save the item to the gallery
	 */
	private function save() : void
	{
		global $smcFunc, $modSettings, $txt, $user_info, $gallerySettings, $sourcedir, $scripturl, $boarddir;
		
		// By default there's an error for the response
		http_response_code(401);

		// Check session
		$checkSession = checkSession('post', 'gallery_upload', false);
		if ($checkSession !== '')
		{
			loadLanguage('Errors');
			echo $txt[$checkSession];
			exit;
		}

		// Check for the file from the beginning...
		if (!isset($_FILES['picture']) || !isset($_FILES['picture']['name']) || $_FILES['picture']['name'] == '')
		{
			echo $txt['gallery_error_no_picture'];
			exit;
		}

		// Pro gallery?
		$this->_gallery_pro = function_exists('CreateGalleryPrettyCategory') ?? false;

		// Gallery Settings
		if (!empty($this->_gallery_pro))
		{
			LoadGallerySettings();
		}

		// Can you post?
		if (!allowedTo('smfgallery_add') && !allowedTo('smfgallery_manage'))
		{
			echo $txt['cannot_smfgallery_add'];
			exit;
		}

		// Get a gallery path
		$modSettings['gallery_path'] = ($modSettings['gallery_path'] ?? $boarddir . '/gallery/');

		// Check if gallery path is writable
		if (!is_writable($modSettings['gallery_path']))
		{
			echo $txt['gallery_write_error'];
			exit;
		}

		// Check upload limit
		$this->CheckMaxUploadPerDay();

		// Options
		$pictureOptions = [
			'id_cat' => (int) $_REQUEST['cat'] ?? 0,
			'id_user' => $user_info['id'],
			'user_group' => $user_info['is_guest'] ? -1 : ($user_info['groups'][0] ?? 0), // In SMF Gallery this is backwards
			'title' => isset($_REQUEST['title']) ? $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($_REQUEST['title']), ENT_QUOTES) : '',
			'description' => isset($_REQUEST['descript']) ? $smcFunc['htmlspecialchars']($_REQUEST['descript'], ENT_QUOTES) : '',
			'keywords' => isset($_REQUEST['keywords']) ? str_replace(',',' ',$smcFunc['htmlspecialchars']($_REQUEST['keywords'], ENT_QUOTES)) : '',
			'upload_location' => $_REQUEST['upload_location'] ?? 'main',
			'autoapprove' => (int) 1,
			'allowcomments' => (int) empty($modSettings['gallery_commentchoice']) ? 1 : ($_REQUEST['allowcomments'] ?? 0),
			'sendemail' => (int) isset($_REQUEST['sendemail']) ? 1 : 0,
			'mature' => (int) isset($_REQUEST['mature']) ? 1 : 0,
			'allowratings' => (int) empty($gallerySettings['gallery_set_allowratings']) ? 0 : ($_REQUEST['allow_ratings'] ?? 0),
			'customfields' => $_REQUEST['custom_field'] ?? [],
			'degrees' => (int) isset($_REQUEST['mature']) ? $_REQUEST['mature'] : 0,
		];
		
		// ERROR: Keywords
		if (!empty($gallerySettings['gallery_set_require_keyword']) && empty($pictureOptions['keywords']))
		{
			echo $txt['gallery_txt_err_require_keyword'];
			exit;
		}

		// ERROR: Category
		if (empty($pictureOptions['id_cat']))
		{
			echo $txt['gallery_error_no_cat'];
			exit;
		}

		// Check if this category is real
		$query = $smcFunc['db_query']('', '
			SELECT c.id_cat' . (!empty($this->_gallery_pro) ? ', c.locked, c.id_board, c.locktopic, c.showpostlink, c.postingsize, c.tweet_items, c.id_topic' : '') . '
			FROM {db_prefix}gallery_cat AS c
			WHERE c.id_cat = {int:cat}',
			[
				'cat' => $pictureOptions['id_cat'],
			]
		);
		$rowcat = $smcFunc['db_fetch_assoc']($query);
		$smcFunc['db_free_result']($query);

		// Check if it's an user category
		if (!empty($this->_gallery_pro))
		{
			$queryuser = $smcFunc['db_query']('', '
				SELECT c.user_id_cat, c.id_member
				FROM {db_prefix}gallery_usercat AS c
				WHERE c.user_id_cat = {int:cat}
					AND c.id_member = {int:member}',
				[
					'cat' => $pictureOptions['id_cat'],
					'member' => $pictureOptions['id_user'],
				]
			);
			$usercat = $smcFunc['db_fetch_assoc']($queryuser);
			$smcFunc['db_free_result']($queryuser);
		}

		// Category doesn't exist?
		if ((empty($rowcat) && $pictureOptions['upload_location'] === 'main') || (empty($usercat) && $pictureOptions['upload_location'] === 'user'))
		{
			echo $txt['gallery_error_no_cat'];
			exit;
		}

		// User can't have a gallery?
		if ($pictureOptions['upload_location'] === 'user' && !allowedTo('smfgallery_usergallery'))
		{
			echo $txt['cannot_smfgallery_usergallery'];
			exit;
		}

		// Attach the new values to the options
		$pictureOptions += [
			'locked' => $rowcat['locked'] ?? 0,
			'id_board' => $rowcat['id_board'] ?? 0,
			'locktopic' => $rowcat['locktopic'] ?? 0,
			'showpostlink' => $rowcat['showpostlink'] ?? 0,
			'postingsize' => $rowcat['postingsize'] ?? 0,
			'tweet_items' => $rowcat['tweet_items'] ?? 0,
			'id_topic' => $rowcat['id_topic'] ?? 0,
		];

		// Fix title
		$pictureOptions['title'] = un_htmlspecialchars($pictureOptions['title']);

		// Custom fields
		if (!empty($this->_gallery_pro) && !empty($pictureOptions['customfields']))
		{
			// Load the fields
			$this->customFields($pictureOptions['id_cat']);

			if (!empty($this->_gallery_cfields))
			{
				foreach($pictureOptions['customfields'] as $field_id => $custom_field)
				{
					// Throw the error while we are here already
					if (empty($custom_field) && !empty($this->_gallery_cfields[$field_id]['is_required']))
					{
						echo $txt['gallery_err_req_custom_field'] . $this->_gallery_cfields[$field_id]['title'];
						exit;
					}

					// It doesn't belong to this category and it's not global
					if (!in_array($field_id, array_keys($this->_gallery_cfields)))
					{
						unset($pictureOptions['customfields'][$field_id]);
					}

					// It was left empty
					elseif (empty($custom_field) && empty($this->_gallery_cfields[$field_id]['is_required']))
					{
						unset($pictureOptions['customfields'][$field_id]);
					}
				}
			}
		}

		// Enable Multi-folder?
		if (!empty($modSettings['gallery_set_enable_multifolder']) && !empty($this->_gallery_pro))
		{
			CreateGalleryFolder();
		}

		// Permissions
		if (!empty($this->_gallery_pro) && !allowedTo('smfgallery_manage') && $pictureOptions !== 'user')
		{
			// Get the permissions
			$this->categoriesPermissions($pictureOptions['id_cat']);
			$permission_error = false;

			// ERROR: Is the category locked?
			if (!empty($pictureOptions['locked']))
			{
				echo $txt['gallery_err_locked_upload'];
				exit;
			}

			// Got permissions?
			if (!empty($this->_gallery_catperm[$pictureOptions['id_cat']]))
			{
				// Can view?
				if ($this->_gallery_catperm[$pictureOptions['id_cat']][$pictureOptions['user_group']]['view'] === '0')
					$permission_error = true;

				// Can add?
				if ($this->_gallery_catperm[$pictureOptions['id_cat']][$pictureOptions['user_group']]['add'] === '0')
					$permission_error = true;

				// Auto Approve?
				if ($this->_gallery_catperm[$pictureOptions['id_cat']][$pictureOptions['user_group']]['approve'] === '0' && !allowedTo('smfgallery_autoapprove'))
					$pictureOptions['autoapprove'] = 0;
			}

			// ERROR: Missing permissions?
			if (!empty($permission_error))
			{
				echo $txt['gallery_user_noperm'];
				exit;
			}
		}

		require_once($sourcedir . '/Subs-Graphics.php');

		// Process Uploaded File
		$image_resized = 0;
		$testGD = get_extension_funcs('gd');
		$gd2 = in_array('imagecreatetruecolor', $testGD) && function_exists('imagecreatetruecolor');
		unset($testGD);

		$sizes = @getimagesize($_FILES['picture']['tmp_name']);
		$orginalfilename = addslashes($_FILES['picture']['name']);

		// Title?
		if (empty($pictureOptions['title']))
		{
			$pictureOptions['title'] = $orginalfilename;
		}

		// No size, then it's probably not a valid pic.
		if ($sizes === false)
        {
            @unlink($_FILES['picture']['tmp_name']);
			echo $txt['gallery_error_invalid_picture'];
			exit;
        }

		// File size
		$filesize = $_FILES['picture']['size'];

		$extensions = [
			1 => 'gif',
			2 => 'jpeg',
			3 => 'png',
			5 => 'psd',
			6 => 'bmp',
			7 => 'tiff',
			8 => 'tiff',
			9 => 'jpeg',
			14 => 'iff',
			18 => 'webp',
		];
		$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';

		if (!empty($this->_gallery_pro))
		{
			$gallerySettings['gallery_set_disallow_extensions'] = trim($gallerySettings['gallery_set_disallow_extensions']);
			$gallerySettings['gallery_set_disallow_extensions'] = str_replace('.', '', $gallerySettings['gallery_set_disallow_extensions']);
			$gallerySettings['gallery_set_disallow_extensions'] = strtolower($gallerySettings['gallery_set_disallow_extensions']);
			$disallowedExtensions = explode(',', $gallerySettings['gallery_set_disallow_extensions']);

			// ERROR: Disallowed extension?
			if (in_array($extension, $disallowedExtensions))
			{
				@unlink($_FILES['picture']['tmp_name']);
				echo $txt['gallery_err_disallow_extensions'] . $extension;
				exit;
			}
		}

		if (strtolower($extension) != 'png')
			$modSettings['avatar_download_png'] = 0;

		// Check min size
		if ((!empty($modSettings['gallery_min_width']) && $sizes[0] < $modSettings['gallery_min_width']) || (!empty($modSettings['gallery_min_height']) && $sizes[1] < $modSettings['gallery_min_height']))
		{
			@unlink($_FILES['picture']['tmp_name']);
            echo $txt['gallery_error_img_size_height2'] . $sizes[1] . $txt['gallery_error_img_size_width2'] . $sizes[0];
			exit;
		}

		// Check max size
		if ((!empty($modSettings['gallery_max_width']) && $sizes[0] > $modSettings['gallery_max_width']) || (!empty($modSettings['gallery_max_height']) && $sizes[1] > $modSettings['gallery_max_height']))
		{
			if (!empty($modSettings['gallery_resize_image']) && !empty($this->_gallery_pro))
			{
				// Check to resize image?
				$exifData = ReturnEXIFData($_FILES['picture']['tmp_name']);
				DoImageResize($sizes, $_FILES['picture']['tmp_name']);
				$image_resized = 1;
				$filesize = filesize($_FILES['picture']['tmp_name']);
			}
			else
			{
				@unlink($_FILES['picture']['tmp_name']);
                echo $txt['gallery_error_img_size_height'] . $sizes[1] . $txt['gallery_error_img_size_width'] . $sizes[0];
				exit;
			}
		}

		// Max filesize
		if (!empty($modSettings['gallery_max_filesize']) && $filesize > $modSettings['gallery_max_filesize'])
		{
			@unlink($_FILES['picture']['tmp_name']);
            echo $txt['gallery_error_img_filesize'] . gallery_format_size($modSettings['gallery_max_filesize'], 2);
			exit;
		}

		// Check Quota
		if (!empty($this->_gallery_pro))
		{
			$quotalimit = GetQuotaGroupLimit($pictureOptions['id_user']);
			$userspace = GetUserSpaceUsed($pictureOptions['id_user']);

			// Check if exceeds quota limit or if there is a quota
			if ($quotalimit != 0  &&  ($userspace + $filesize) >  $quotalimit)
			{
				@unlink($_FILES['picture']['tmp_name']);
				echo $txt['gallery_error_space_limit'] . gallery_format_size($userspace, 2) . ' / ' . gallery_format_size($quotalimit, 2);
				exit;
			}
		}

		// File Name
		$filename = $pictureOptions['id_user'] . '-' . date('dmyHis') . '.' . $extension;

		// Extra folder
		$extrafolder = '';
		if (!empty($modSettings['gallery_set_enable_multifolder']) && !empty($this->_gallery_pro))
		{
			$extrafolder = $modSettings['gallery_folder_id'] . '/';
		}

		move_uploaded_file($_FILES['picture']['tmp_name'], $modSettings['gallery_path'] . $extrafolder .  $filename);
		@chmod($modSettings['gallery_path'] . $extrafolder .  $filename, 0644);

		// Thumbnails
		$thumbname = 'thumb_' . $filename;

		// Thumbnail and rotate
		if (!empty($this->_gallery_pro))
		{
			GalleryCreateThumbnail($modSettings['gallery_path'] . $extrafolder .  $filename, $modSettings['gallery_thumb_width'], $modSettings['gallery_thumb_height']);

			// Rotate
			if (!empty($pictureOptions['degrees']))
			{
				GalleryRotateImage($modSettings['gallery_path'] . $extrafolder .  $filename, $pictureOptions['degrees']);
				$sizes = @getimagesize($modSettings['gallery_path'] . $extrafolder .  $filename);
			}
		}
		// Create thumbnail
		else
		{
			createThumbnail($modSettings['gallery_path'] . $filename, $modSettings['gallery_thumb_width'], $modSettings['gallery_thumb_height']);
		}
		rename($modSettings['gallery_path'] . $extrafolder .  $filename . '_thumb',  $modSettings['gallery_path'] . $extrafolder .  'thumb_' . $filename);
		@chmod($modSettings['gallery_path'] . $extrafolder .  'thumb_' . $filename, 0755);

		// Resized?
		if ($image_resized)
		{
			$sizes = @getimagesize($modSettings['gallery_path'] . $extrafolder .  $filename);
		}

		// Medium Image
		$mediumimage = '';
		if (!empty($modSettings['gallery_make_medium']) && !empty($this->_gallery_pro))
		{
			GalleryCreateThumbnail($modSettings['gallery_path'] . $extrafolder .  $filename, $modSettings['gallery_medium_width'], $modSettings['gallery_medium_height']);
			rename($modSettings['gallery_path'] . $extrafolder .  $filename . '_thumb',  $modSettings['gallery_path'] . $extrafolder .  'medium_' . $filename);
			$mediumimage = 'medium_' . $filename;
			@chmod($modSettings['gallery_path'] . $extrafolder .  'medium_' . $filename, 0755);

			// Check for Watermark
			DoWaterMark($modSettings['gallery_path'] . $extrafolder .  'medium_' .  $filename);
		}

		// Insert the picture
		if (!empty($this->_gallery_pro))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}gallery_pic',
				[
					'id_cat' => 'int', 'user_id_cat' => 'int',
					'filesize' => 'int', 'thumbfilename' => 'string', 'mediumfilename' => 'string', 'filename' => 'string','orginalfilename' => 'string',
					'height' => 'int', 'width' => 'int',
					'title' => 'string', 'description' => 'string', 'keywords' => 'string',
					'id_member' => 'int', 'date' => 'int',
					'approved' => 'int', 'allowcomments' => 'int', 'allowratings' => 'int',
					'sendemail' => 'int',  'mature' => 'int',
				],
				[
					($pictureOptions['upload_location'] === 'main' ? $pictureOptions['id_cat'] : 0), $pictureOptions['upload_location'] === 'user' ? $pictureOptions['id_cat'] : 0,
					$filesize, $extrafolder . $thumbname, $extrafolder . $mediumimage, $extrafolder . $filename, $orginalfilename,
					$sizes[1], $sizes[0],
					$pictureOptions['title'], $pictureOptions['description'], $pictureOptions['keywords'],
					$pictureOptions['id_user'], time(),
					$pictureOptions['autoapprove'], $pictureOptions['allowcomments'], $pictureOptions['allowratings'],
					$pictureOptions['sendemail'], $pictureOptions['mature'],
				],
				['id_picture']
			);
		}
		// Regular gallery
		else
		{
			$smcFunc['db_insert']('',
			'{db_prefix}gallery_pic',
			[
				'id_cat' => 'int', 'user_id_cat' => 'int',
				'filesize' => 'int', 'thumbfilename' => 'string', 'mediumfilename' => 'string', 'filename' => 'string','orginalfilename' => 'string',
				'height' => 'int', 'width' => 'int',
				'title' => 'string', 'description' => 'string', 'keywords' => 'string',
				'id_member' => 'int', 'date' => 'int',
				'approved' => 'int', 'allowcomments' => 'int', 'allowratings' => 'int'
			],
			[
				($pictureOptions['upload_location'] === 'main' ? $pictureOptions['id_cat'] : 0), $pictureOptions['upload_location'] === 'user' ? $pictureOptions['id_cat'] : 0,
				$filesize, $extrafolder . $thumbname, $extrafolder . $mediumimage, $extrafolder . $filename, $orginalfilename,
				$sizes[1], $sizes[0],
				$pictureOptions['title'], $pictureOptions['description'], $pictureOptions['keywords'],
				$pictureOptions['id_user'], time(),
				$pictureOptions['autoapprove'], $pictureOptions['allowcomments'], $pictureOptions['allowratings']
			],
			['id_picture']
		);
		}

		// The inserted picture
		$gallery_pic_id = $smcFunc['db_insert_id']('{db_prefix}gallery_pic', 'id_picture');

		// Additional options
		if (!empty($this->_gallery_pro))
		{
			// Insert the custom fields
			if (!empty($pictureOptions['customfields']))
			{
				// Add the picture
				foreach($pictureOptions['customfields'] as $i_field_id => $i_customfields)
				{
					$pictureOptions['customfields'][$i_field_id] = [
						'id_custom' => $i_field_id,
						'id_picture' => $gallery_pic_id,
						'value' => $i_customfields,
					];
				}
				$smcFunc['db_insert']('', '
					{db_prefix}gallery_custom_field_data',
					[
						'id_custom' => 'int',
						'id_picture' => 'int',
						'value' => 'string',
					],
					$pictureOptions['customfields'],
					['id_picture'],
				);
			}

			if (!empty($exifData))
			{
				ProcessEXIFData($extrafolder . $filename, $gallery_pic_id, $exifData);
			}

			Gallery_AddRelatedPicture($gallery_pic_id, $pictureOptions['title']);
			Gallery_AddToActivityStream('galleryproadd', $gallery_pic_id, $pictureOptions['title'], $pictureOptions['id_user']);

			// If we are using multifolders get the next folder id
			if ($modSettings['gallery_set_enable_multifolder'])
				ComputeNextFolderID($gallery_pic_id);

			// Posting in a board?
			if ($pictureOptions['upload_location'] === 'main' && !empty($pictureOptions['autoapprove']))
			{
				$extraheightwidth = '';
				if ($pictureOptions['postingsize'] == 1)
				{
					$postimg = $filename;
					$extraheightwidth = " height={$sizes[1]} width={$sizes[0]}";
				}
				else
				{
					$postimg = $thumbname;
				}

				// Create the post
				require_once($sourcedir . '/Subs-Post.php');
				require_once($sourcedir . '/Post.php');

				// Post link
				$showpostlink = ($pictureOptions['showpostlink'] == 1 ? '\\n\\n' . $scripturl . '?action=gallery;sa=view;id=' . $gallery_pic_id : '');

				// Options
				$msgOptions = [
					'id' => 0,
					'subject' => $pictureOptions['title'],
					'body' => '[b]' . $pictureOptions['title'] . '[/b]\\n\\n[img$extraheightwidth]' . $modSettings['gallery_url']  . $extrafolder . $postimg . '[/img]' . $showpostlink . '\\n\\n' . $pictureOptions['description'],
					'icon' => 'xx',
					'smileys_enabled' => 1,
					'attachments' => [],
				];
				$topicOptions = [
					'id' => $pictureOptions['id_topic'],
					'board' => $pictureOptions['id_board'],
					'poll' => null,
					'lock_mode' => $pictureOptions['locktopic'],
					'sticky_mode' => null,
					'mark_as_read' => true,
				];
				$posterOptions = [
					'id' => $user_info['id'],
					'update_post_count' => !$user_info['is_guest'],
				];

				// Fix height & width of posted image in message
				preparsecode($msgOptions['body']);

				// Post it
				createPost($msgOptions, $topicOptions, $posterOptions);

				if (function_exists("notifyMembersBoard"))
				{
					$notifyData = [
						'body' =>$msgOptions['body'],
						'subject' => $msgOptions['subject'],
						'name' => $user_info['name'],
						'poster' => $user_info['id'],
						'msg' => $msgOptions['id'],
						'board' =>  $pictureOptions['id_board'],
						'topic' => $topicOptions['id'],
					];
					notifyMembersBoard($notifyData);
				}
				else
				{
					// for 2.1
					$smcFunc['db_insert']('',
						'{db_prefix}background_tasks',
						['task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'],
						['$sourcedir/tasks/CreatePost-Notify.php', 'CreatePost_Notify_Background', $smcFunc['json_encode']([
							'msgOptions' => $msgOptions,
							'topicOptions' => $topicOptions,
							'posterOptions' => $posterOptions,
							'type' =>  $topicOptions['id'] ? 'reply' : 'topic',
						]), 0],
						['id_task']
					);

				}

				// Update the picture
				$smcFunc['db_query']('','
					UPDATE {db_prefix}gallery_pic
					SET
						id_topic = {int:topic},
						id_msg = {int:msg}
					WHERE id_picture = {int:id_pic}',
					[
						'topic' => $topicOptions['id'],
						'msg' => $msgOptions['id'],
						'id_pic' => $gallery_pic_id,
					]
				);

           		Gallery_InsertSMFTags($pictureOptions['keywords'], $topicOptions['id']);
			}

			// Last recheck Image if it was resized
			if ($image_resized == 1)
			{
				RecheckResizedImage($modSettings['gallery_path'] . $extrafolder .  $filename, $gallery_pic_id, $filesize,$user_info['id']);
			}

			// Check for Watermark
			DoWaterMark($modSettings['gallery_path'] . $extrafolder .  $filename);

			// User galleries?
			if ($pictureOptions['upload_location'] === 'user')
			{
				UpdateUserCategoryTotals($pictureOptions['id_cat']);

				if (!empty($pictureOptions['autoapprove']))
				{
					Gallery_UpdateUserLatestCategory($pictureOptions['id_cat']);
				}

			}
			else
			{
				UpdateCategoryTotals($pictureOptions['id_cat']);

				if (!empty($pictureOptions['autoapprove']))
				{
					if ($pictureOptions['tweet_items'] == 1)
						Gallery_TweetItem($pictureOptions['title'], $gallery_pic_id);

					Gallery_UpdateLatestCategory($pictureOptions['id_cat']);
				}
			}

			// Update keywords
			UpdateGalleryKeywords($gallery_pic_id);

			// Other
			if (!empty($pictureOptions['autoapprove']))
			{
				UpdateMemberPictureTotals($pictureOptions['id_user']);
				SendMemberWatchNotifications($pictureOptions['id_user'], $scripturl . '?action=gallery;sa=view;id=' .  $gallery_pic_id);

				// Add Post Count
				if (!empty($gallerySettings['gallery_set_picturepostcount']))
				{
					if ($pictureOptions['id_user'] != 0)
					{
						updateMemberData($pictureOptions['id_user'], ['posts' => '+']);
					}
				}
			}
			else
			{
				$body = $txt['gallery_txt_itemwaitingapproval2'];
				$body = str_replace('%url', $scripturl . '?action=admin;area=gallery;sa=approvelist', $body);
				$body = str_replace('%title', $pictureOptions['title'], $body);
				Gallery_emailAdmins($txt['gallery_txt_itemwaitingapproval'], $body);
			}
		}

		// Badge Awards Mod Check
		GalleryCheckBadgeAwards($pictureOptions['id_user']);

		// Can't insert if you can't auto-approve
		if (empty($pictureOptions['autoapprove']))
		{
			echo $txt['gallery_error_pic_notapproved'];
			exit;
		}

		// Success
		http_response_code(200);

		// Success
		$reponse = [
			'message' => $txt['gallery_txt_multi_imageuploaded'] . ' ' . $pictureOptions['title'],
			'picture_src' => $modSettings['gallery_url'] . $extrafolder .  $filename,
		];
		echo json_encode($reponse);

		// Out
		exit;
	}

	/**
	 * Get categories permissions
	 */
	private function categoriesPermissions(array|int $category = 0) : void
	{
		global $smcFunc;

		// If it's not an array, make it so!
		if (!is_array($category))
			$category = (empty($category) ? [] : [$category]);
		else
			$category = array_unique($category);

		// Get the permissions
		$permquery = $smcFunc['db_query']('', '
			SELECT p.id_cat, p.view, p.addpic, p.id_group, p.autoapprove
			FROM {db_prefix}gallery_catperm AS p
				LEFT JOIN {db_prefix}gallery_cat AS c ON (c.id_cat = p.id_cat)
			WHERE c.redirect = {int:redirect}' . (empty($category) ? '' : '
				AND p.id_cat IN ({array_int:category})') . '
			ORDER BY c.title ASC',
			[
				'redirect' => 0,
				'category' => $category,
			]
		);

		$this->_gallery_catperm = [];
		while($row = $smcFunc['db_fetch_assoc']($permquery))
		{
			$this->_gallery_catperm[$row['id_cat']][$row['id_group']] = [
				'view' => $row['view'],
				'add' => $row['addpic'],
				'approve' => $row['autoapprove'],
			];
		}
		$smcFunc['db_free_result']($permquery);
	}

	/**
	 * Check if the user reached the max uploads per day.
	 */
	public function CheckMaxUploadPerDay() : void
	{
		global $gallerySettings, $smcFunc, $user_info, $txt;
	
		if (empty($gallerySettings['gallery_set_maxuploadperday']) || allowedTo('smfgallery_manage') || empty($this->_gallery_pro))
			return;

		// Find total uploads for the last 24 hours for the user
		$currenttime = time();
		$last24hourstime = $currenttime  -  (1* 24 * 60 * 60);

		$dbresult = $smcFunc['db_query']('', '
			SELECT id_picture
			FROM {db_prefix}gallery_pic
			WHERE id_member = {int:user}
				AND date > {int:time}',
			[
				'user' => $user_info['id'],
				'time' => $last24hourstime
			]
		);
		$totalRow['total'] = $smcFunc['db_num_rows']($dbresult);
	
		if ($totalRow['total'] >= $gallerySettings['gallery_set_maxuploadperday'])
		{
			echo $txt['gallery_err_upload_day_limit'] .  $gallerySettings['gallery_set_maxuploadperday'];
			exit;
		}
	}

	/**
	 * Get the custom fields
	 */
	private function customFields(int $category = 0, bool $required = false)
	{
		global $smcFunc;

		$result = $smcFunc['db_query']('', '
			SELECT f.id_custom, f.id_cat, f.title, f.defaultvalue, f.is_required
			FROM  {db_prefix}gallery_custom_field as f'. (!empty($category) ? '
			WHERE f.id_cat = {int:cat}
				OR f.id_cat = {int:global}' : '') . '
			ORDER BY f.is_required DESC',
			[
				'required' => 1,
				'cat' => $category,
				'global' => 0,
			]
		);

		$this->_gallery_cfields = [];
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			// Only required fields?
			if (!empty($required) && !empty($row['is_required']))
				continue;

			// Fields
			$this->_gallery_cfields[$row['id_custom']] = [
				'id_cat' => $row['id_cat'],
				'title' => $row['title'],
				'default' => $row['defaultvalue'],
				'is_required' => $row['is_required'],
			];
		}
		$smcFunc['db_free_result']($result);
	}
}
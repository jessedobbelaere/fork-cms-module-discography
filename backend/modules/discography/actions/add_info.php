<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * This is the add-action, it will display a form to create a new item
 *
 * @author Jesse Dobbelaere <jesse@dobbelaere-ae.be>
 */
class BackendDiscographyAddInfo extends BackendBaseActionAdd
{
	/**
	 * Execute the action
	 */
	public function execute()
	{
		parent::execute();

		$this->loadData();
		$this->loadForm();
		$this->validateForm();

		$this->parse();
		$this->display();
	}

	/**
	 * Load the item data
	 */
	protected function loadData()
	{
		// Check parameters
		$this->id = $this->getParameter('id', 'int', null);
		if($this->id == null || !BackendDiscographyModel::exists($this->id))
		{
			$this->redirect(
				BackendModel::createURLForAction('index') . '&error=non-existing'
			);
		}

		// Get data
		$item = BackendDiscographyModel::get($this->id);
		$this->title = $item['title'];
		$this->meta_url = BackendDiscographyModel::getMetaUrl($item['meta_id']);
	}

	/**
	 * Load the form
	 */
	protected function loadForm()
	{
		// create form
		$this->frm = new BackendForm('add');

		// set hidden values
		$rbtHiddenValues[] = array('label' => BL::lbl('Hidden'), 'value' => 'Y');
		$rbtHiddenValues[] = array('label' => BL::lbl('Published'), 'value' => 'N');

		// use POST values to rebuild the tracks
		$this->tracks = array();
		if($this->frm->isSubmitted())
		{
			if(isset($_POST['tracks']) && is_array($_POST['tracks']))
			{
				foreach($_POST['tracks'] as $track)
				{
					$chunks = explode(':::::', $track);
					if(count($chunks) == 3)
					{
						$this->tracks[] = array(
							'id' => ($chunks[0] == '') ? '' : (int) $chunks[0],
							'track' => (string) $chunks[1],
							'duration' => (string) $chunks[2],
						);
					}
				}
			}
		}
		$this->tpl->assign('tracks', json_encode($this->tracks));

		// get categories
		$categories = BackendDiscographyModel::getCategories();

		// create elements
		$this->frm->addRadiobutton('hidden', $rbtHiddenValues, 'N');
		$this->frm->addDate('release_date');
		$this->frm->addDropdown('category_id', $categories, SpoonFilter::getGetValue('category', null, null, 'int'));
		if(count($categories) != 2) $this->frm->getField('category_id')->setDefaultElement('');
		$this->frm->addImage('image');
		$this->frm->addCheckbox('delete_image');
		$this->frm->addText('track')->setAttributes(array('class' => 'inputText', 'style' => 'width: 300px'));
		$this->frm->addText('duration')->setAttributes(array('class' => 'inputText', 'type' => 'time'));
		$this->frm->addHidden('dummy_tracks');
	}

	/**
	 * Parse the page
	 */
	protected function parse()
	{
		parent::parse();

		// extra vars
		$this->tpl->assign('id', $this->id);
		$this->tpl->assign('title', $this->title);
	}

	/**
	 * Validate the form
	 */
	protected function validateForm()
	{
		if($this->frm->isSubmitted())
		{
			$this->frm->cleanupFields();

			// validation
			$fields = $this->frm->getFields();
			$fields['release_date']->isValid(BL::err('DateIsInvalid'));
			$fields['category_id']->isFilled(BL::err('FieldIsRequired'));

			// not enough tracks
			if(count($this->tracks) == 0)
			{
				$this->tpl->assign('noTracks', true);
				$this->frm->addError('noTracks');
			}

			// no errors?
			if($this->frm->isCorrect())
			{
				$item['hidden'] = $fields['hidden']->getValue();
				$item['release_date'] = date('Y-m-d', strtotime(str_replace('/', '-', $fields['release_date']->getValue())));
				$item['category_id'] = $fields['category_id']->getValue();

				// add image
				if($fields['image']->isFilled())
				{
					$item['image'] = $fields['image'];
					$imagePath = FRONTEND_FILES_PATH . '/discography/images';

					// create folders if needed
					if(!SpoonDirectory::exists($imagePath . '/source')) SpoonDirectory::create($imagePath . '/source');
					if(!SpoonDirectory::exists($imagePath . '/50x50')) SpoonDirectory::create($imagePath . '/50x50');
					if(!SpoonDirectory::exists($imagePath . '/128x128')) SpoonDirectory::create($imagePath . '/128x128');
					if(!SpoonDirectory::exists($imagePath . '/150x150')) SpoonDirectory::create($imagePath . '/150x150');
					if(!SpoonDirectory::exists($imagePath . '/300x300')) SpoonDirectory::create($imagePath . '/300x300');

					// build the image name
					$item['image'] = $this->meta_url . '.' . $fields['image']->getExtension();

					// upload the image & generate thumbnails
					$fields['image']->generateThumbnails($imagePath, $item['image']);
				}

				BackendDiscographyModel::update($this->id, $item);

				// update/insert the tracks
				foreach($this->tracks as $i => $track)
				{
					// insert track
					BackendDiscographyModel::insertTrack(
						array(
							'album_id' => $this->id,
							'title' => $track['track'],
							'duration' => $track['duration'],
							'sequence' => $i + 1
						)
					);
				}

				BackendModel::triggerEvent(
					$this->getModule(), 'after_add', $item
				);
				$this->redirect(
					BackendModel::createURLForAction('index') . '&report=added&var=' . urlencode($this->title) . '&highlight=row-' . $this->id
				);
			}
		}
	}
}

<?php

namespace Backend\Modules\Discography\Actions;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Backend\Core\Engine\Base\ActionEdit as BackendBaseActionEdit;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Discography\Engine\Model as BackendDiscographyModel;
use Backend\Core\Engine\Form as BackendForm;
use Backend\Core\Engine\Language as BL;
use Backend\Core\Engine\Meta as BackendMeta;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;

/**
 * This is the edit-action, it will display a form with the item data to edit
 *
 * @author Jesse Dobbelaere <jesse@dobbelaere-ae.be>
 */
class Detail extends BackendBaseActionEdit
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
        $this->id = $this->getParameter('id', 'int', null);
        if($this->id == null || !BackendDiscographyModel::exists($this->id)) {
            $this->redirect(
                BackendModel::createURLForAction('Index') . '&error=non-existing'
            );
        }

        $this->record = BackendDiscographyModel::get($this->id);
    }

    /**
     * Load the form
     */
    protected function loadForm()
    {
        // set hidden values
        $rbtVisibleValues[] = array(
            'label' => BL::lbl('Hidden'),
            'value' => 'N'
        );
        $rbtVisibleValues[] = array(
            'label' => BL::lbl('Published'),
            'value' => 'Y'
        );

        // create form
        $this->frm = new BackendForm('edit');
        $this->frm->addText(
            'title', $this->record['title'], null,
            'inputText title', 'inputTextError title'
        );
        $this->frm->addRadiobutton(
            'visible', $rbtVisibleValues, $this->record['visible']
        );

        // meta
        $this->meta = new BackendMeta(
            $this->frm, $this->record['meta_id'], 'title', true
        );
        $this->meta->setUrlCallback('Backend\Modules\Discography\Engine\Model', 'getUrl', array($this->record['id']));
    }

    /**
     * Parse the page
     */
    protected function parse()
    {
        parent::parse();
        $this->tpl->assign('item', $this->record);

        // get url
        $url = BackendModel::getURLForBlock($this->URL->getModule(), 'detail');
        $url404 = BackendModel::getURL(404);
        if($url404 != $url) $this->tpl->assign('detailURL', SITE_URL . $url);
    }

    /**
     * Validate the form
     */
    protected function validateForm()
    {
        if($this->frm->isSubmitted()) {
            $this->frm->cleanupFields();

            // validation
            $fields = $this->frm->getFields();
            $fields['title']->isFilled(BL::err('FieldIsRequired'));
            $this->meta->validate();

            if($this->frm->isCorrect()) {
                $item['meta_id'] = $this->meta->save(true);
                $item['title'] = $fields['title']->getValue();
                $item['language'] = BL::getWorkingLanguage();
                $item['visible'] = $fields['visible']->getValue();

                BackendDiscographyModel::update($this->id, $item);
                $item['id'] = $this->id;

                BackendSearchModel::saveIndex(
                    $this->getModule(),
                    $item['id'],
                    array('title' => $item['title'], 'text' => $item['title'])
                );

                BackendModel::triggerEvent(
                    $this->getModule(), 'after_edit', $item
                );
                $this->redirect(
                    BackendModel::createURLForAction('Index') . '&report=edited&highlight=row-' . $item['id']
                );
            }
        }
    }
}

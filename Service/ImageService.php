<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Service;

use \LC\Config;
use \LC\Factory;

class ImageService extends Service
{
    protected $imageRepository;
    protected $albumRepository;

    protected $allowedKeys=array(
        'album_id', 'name', 'describe', 'filename', 'pos',
        'updated', 'total_votes', 'vote_result', 'rating'
    );

    public $maxfilesize=5242880;//5*1024*1024;

    public function __construct(
        \LCM\lc_album\Repository\ImageRepository $imageRepository,
        \LCM\lc_album\Repository\AlbumRepository $albumRepository
    ) {
        $this->imageRepository=$imageRepository;
        $this->albumRepository=$albumRepository;
        $this->validation=\HTML_QuickForm2_Rule::SERVER | \HTML_QuickForm2_Rule::CLIENT;
    }

    public function add($data)
    {
        $image=$this->imageRepository->add($data);
        //$image->filename=$this->imageRepository->uploadImage($data['Filedata']);
        //$this->imageRepository->save($image);
        return $image;
    }

    public function delete($image)
    {
        return $this->imageRepository->delete($image);
    }

    public function deleteByAlbum($album)
    {
        do {
            $images=$this->getByAlbum($album, 1, 500);
            $ids=array();
            foreach ($images['collection'] as $image) {
                $this->imageRepository->deleteFiles($image);
                $ids[]=$image->id;
            }
            $this->imageRepository->deleteByArrayId($ids);
        } while ($images['totalCount']>count($images['collection']));
    }
    
    public function update($image, $data)
    {
        if ($data['Filedata']['tmp_name']) {
            if ($filename=$this->imageRepository->uploadImage($data['Filedata'])) {
                $data['filename']=$filename;
            } else {
                return;
            }
        }
        $this->filterValues($data);
        $image->set($data);
        $this->imageRepository->save($image);
        return $item;
    }

    public function getById($id)
    {
        return $this->imageRepository->getById($id);
    }

    public function getByAlbum($album, $page, $atPage)
    {
        return $this->imageRepository->getByAlbum($album, $page, $atPage);
    }

    public function getNext($image)
    {
        return $this->imageRepository->getNext($image);
    }

    public function getPrevious($image)
    {
        return $this->imageRepository->getPrevious($image);
    }

    public function sortImages($images, $data)
    {
        foreach ($images as $image) {
            $image->pos=intval($data[$image->id]);
            $this->imageRepository->save($image);
        }
    }

    public function buildEditForm($image = null)
    {
        $form = Factory::create(
            'HTML_QuickForm2',
            null,
            'post',
            array('action' =>'', 'enctype' =>'multipart/form-data')
        );
        $token=$form->addElement('hidden', 'token', array('value' =>getToken()));
        $frame=$form->addElement('fieldset')->setLabel(_m('Редактирование изображения'));
        $frame->addElement(
            'static',
            'image',
            null,
            array('content' =>'<image src="'.$image->files['small'].'" alt="" />')
        );

        $name=$frame->addElement('text', 'name', null, array('label' => _m('Название:')));
        $descr=$frame->addElement(
            'textarea',
            'describe',
            array('class' =>'bbcode',  'rows' =>'10', 'cols' =>'50'),
            array('label' => _m('Описание:'))
        );
        $file=$frame->addElement('file', 'Filedata', null, array('label' =>_m('Заменить файл')));
        //TODO выделить формы в отдельный класс
        $frame->addElement(
            'select',
            'album_id',
            null,
            array('label' =>_m('Переместить в альбом'),
            'options' => $this->albumRepository->getAllAsArray())
        );

        $submit=$frame->addElement('submit', 'save', array('value' =>_m('Сохранить')));
        $submit=$frame->addElement(
            'submit',
            'next',
            array('value' =>_m('Сохранить и перейти к следующему'))
        );

        $file->addRule(
            'mimetype',
            _m('Данный тип файла не поддерживается'),
            array('image/gif', 'image/png', 'image/jpeg', 'image/pjpeg')
        );
        $file->addRule(
            'maxfilesize',
            sprintf(
                _m('Файл слишком большой. Разрешены файлы не больше %s'),
                $this->maxfilesize
            ),
            $this->maxfilesize
        );

        if ($image) {
            $form->addDataSource(
                Factory::create(
                    'HTML_QuickForm2_DataSource_Array',
                    $image->getArray()
                )
            );
        }
        return $form;
    }

    public function buildUploadForm()
    {
        $form = \LC\Factory::create(
            'HTML_QuickForm2',
            null,
            'post',
            array('action' =>'#', 'enctype' =>'multipart/form-data')
        );
        $token=$form->addElement('hidden', 'token', array('value' =>getToken()));
        $frame=$form->addElement('fieldset')->setLabel(_m('Загрузка изображения'));
        $file=$frame->addElement('file', 'Filedata', null, array('label' =>_m('Выберите файл')));

        $name=$frame->addElement('text', 'name', null, array('label' => _m('Название:')));
        $descr=$frame->addElement('textarea', 'describe', null, array('label' => _m('Описание:')));
        $submit=$frame->addElement('submit', null, array('value' =>_m('Загрузить')));

        $file->addRule('required', _m('Необходимо указать файл'), null, $this->validation);

        $file->addRule(
            'mimetype',
            _m('Данный тип файла не поддерживается'),
            array('image/gif', 'image/png', 'image/jpeg', 'image/pjpeg')
        );
        $file->addRule(
            'maxfilesize',
            sprintf(
                _m('Файл слишком большой. Разрешены файлы не больше %s'),
                get_formatted_filesize($this->maxfilesize)
            ),
            $this->maxfilesize
        );
        return $form;
    }
}

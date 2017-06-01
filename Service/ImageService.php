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

    public $maxfilesize=5242880;//5*1024*1024;

    public function __construct(
        \LCM\lc_album\Repository\ImageRepository $imageRepository,
        \LCM\lc_album\Repository\AlbumRepository $albumRepository,
        \LC\Tpl $tpl)
    {
        $this->imageRepository=$imageRepository;
        $this->albumRepository=$albumRepository;
        $this->validation=\HTML_QuickForm2_Rule::SERVER | \HTML_QuickForm2_Rule::CLIENT;
    }

    public function add($data)
    {
        $image=$this->imageRepository->create($data);
        $image->filename=$this->uploadImage($data['Filedata']);
        $this->imageRepository->save($image);
        return $image;
    }

    public function delete($image)
    {
        $this->deleteFiles($image);
        return $this->imageRepository->delete($image);
    }

    public function deleteByAlbum($album)
    {
        do {
            $images=$this->getByAlbum($album, 1, 500);
            $ids=array();
            foreach ($images['collection'] as $image) {
                $this->deleteFiles($image);
                $ids[]=$image->id;
            }
            $this->imageRepository->deleteByArrayId($ids);
        } while($images['totalCount']>count($images['collection']));
    }
    
    public function update($item, $data)
    {
        if ($data['Filedata']['tmp_name']) {
            if ($filename=$this->uploadImage($data['Filedata'])) {
                $data['filename']=$filename;
            } else {
                return;
            }
        }

        $this->imageRepository->update($item, $data);
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

    public function getImageFiles($image)
    {
        $files=array();
        foreach (Config::get('tn') as $key => $item) {
            $files[$key]=Config::get('basepath').$item['path'].'/'.$image->filename;
        }
        return $files;
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
        $files=$this->getImageFiles($image);
        $frame->addElement(
            'static',
            'image',
            null,
            array('content' =>'<image src="'.$files['small'].'" alt="" />')
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
        $submit=$frame->addElement('submit', 'next', array('value' =>_m('Сохранить и перейти к следующему')));

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
                    $image->getData()
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

    public function uploadImage($file)
    {
        foreach (Config::get('tn') as $tn) {
            if (!is_dir(LAB_PATH_ROOT.Config::get('basepath').$tn['path'])) {
                mkdir(LAB_PATH_ROOT.Config::get('basepath').$tn['path'], 0777, true);
            }
        }

        $img_info=getimagesize($file['tmp_name']);
        if (!($img_info[2]>0 && $img_info[2]<4)) {
            return LabCMS::i()->error(_m('Недопустимый формат файла'));
        }

        $basename=preg_replace('/\.[^.]+$/', '', $file['name']);
        $ctn=Config::get('tn');
        if ($ctn['original']['type']) {
            $def_ext=$ctn['original']['type'];
        } else {
            $def_ext=image_type_to_extension($img_info[2], false);
        }
        $basename=$this->prepareFilename($basename, $def_ext);

        foreach (Config::get('tn') as $key => $tn) {
            $folder=$tn['path'];
            $img=Factory::create('Image_Resize');
            if ($tn['type']) {
                $ext=$img->new_type=$tn['type'];
            } else {
                $ext=$def_ext;
            }

            if ('small'==$key) {
                if (!empty($tn['type'])) {
                    $name=$basename.'.'.$tn['type'];
                } else {
                    $name=$basename.'.'.$def_ext;
                }
            }

            $img->file= $file['tmp_name'];
            $img->file_dest= LAB_PATH_ROOT.Config::get('basepath').$tn['path'].'/'.$basename.'.'.$ext;
            $img->width=$tn['width'];
            $img->height=$tn['height'];
            if (!empty($tn['crop'])) {
                $img->crop=$tn['crop'];
            }
            if ($img->go()) {
                $done[]= $img->file_dest;
            } else {
                foreach ($done as $file) {
                    unlink($file);
                    return LabCMS::i()->error(_m('Ошибка генерации превью'));
                }
            }
        }

        return $name;

    }

    protected function prepareFilename($name, $ext)
    {
        $name=translit($name);
        return $this->uniqueFilename($name, $ext);
    }

    protected function uniqueFilename($name, $ext)
    {
        $ctn=Config::get('tn');
        while (file_exists(LAB_PATH_ROOT.Config::get('basepath').$ctn['original']['path'].'/'.$name.'.'.$ext)) {
            $name.='_1';
        }
        return $name;
    }

    public function deleteFiles($image)
    {
        $files=$this->getImageFiles($image);
        foreach ($files as $file) {
            unlink(LAB_PATH_ROOT.$file);
        }
    }
}
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

class AlbumService extends Service
{
    protected $allowedKeys=array('name', 'describe', 'logo', 'pos');

    protected $imageRepository;
    protected $albumRepository;

    public function __construct(
        \LCM\lc_album\Repository\ImageRepository $imageRepository,
        \LCM\lc_album\Repository\AlbumRepository $albumRepository)
    {
        $this->imageRepository=$imageRepository;
        $this->albumRepository=$albumRepository;
        $this->validation=\HTML_QuickForm2_Rule::SERVER | \HTML_QuickForm2_Rule::CLIENT;
    }

    public function create($data)
    {
        $this->filterValues($data);
        $album=$this->albumRepository->add($data);
        $album->pos=$album->id;
        $this->albumRepository->save($album);
        return $album;
    }

    public function update($album, $data)
    {
        $this->filterValues($data);
        $album->setData($data);
        $this->albumRepository->save($album);
    }

    public function delete($album)
    {
        $this->deleteFiles($album);
        return $this->albumRepository->delete($album);
    }

    public function getById($id)
    {
        return $this->albumRepository->getById($id);
    }

    public function getByPage($page, $atPage)
    {
        return $this->albumRepository->getByPage($page, $atPage);
    }

    public function getByArrayId($ids)
    {
        return $this->albumRepository->getByArrayId($ids);
    }

    public function getNext($album)
    {
        return $this->albumRepository->getNext($album);
    }

    public function getPrevious($album)
    {
        return $this->albumRepository->getPrevious($album);
    }

    public function moveUp($album)
    {
        if ($prev=$this->albumRepository->getPrevious($album)) {
            $this->swapPos($prev, $album);
        }
    }

    public function moveDown($album)
    {
        if ($next=$this->albumRepository->getNext($album)) {
            $this->swapPos($next, $album);
        }
    }

    public function setLogo($album, $image)
    {
        $file=$this->getSmallImageUrl($image);
        $dir=LAB_PATH_ROOT.Config::get('basepath').Config::get('logo_path');
        if (!is_dir($dir) && !mkdir($dir, 0777)) {
            return LabCMS::i()->error(sprintf(_m("Не могу создать директорию %s"), $dir));
        }
        $album->logo=basename($file);
        $this->albumRepository->save($album);
        copy(LAB_PATH_ROOT.$file, $dir.'/'.$album->logo);
        return true;
    }

    public function getLogoUrl($album)
    {
        if (!$album->logo) return '';
        return Config::get('basepath').Config::get('logo_path').'/'.$album->logo;
    }

    public function getSmallImageUrl($image)
    {
        $tn=Config::get('tn');
        return Config::get('basepath').$tn['small']['path'].'/'.$image->filename;
    }

    protected function swapPos($album, $album2)
    {
        $old_pos=$album2->pos;
        $album2->pos=$album->pos;
        $album->pos=$old_pos;
        $this->albumRepository->save($album);
        $this->albumRepository->save($album2);
    }


    public function addImage($album, $image)
    {
        $data['pos']=$this->imageRepository->getMaxPos($item)+1;
        $image=$this->imageRepository->save($image);

        //если у альбома еще нет логотипа - устанавливаем его
        if (!$album->logo) {
            $this->setLogo($album, $image);
        }
        return $image;
    }

    public function deleteFiles($album)
    {
        if ($file=$this->getLogoUrl($album))
        {
            unlink(LAB_PATH_ROOT.$file);
        }
    }

    public function buildEditForm($album = null)
    {
        $form = Factory::create('HTML_QuickForm2', null, 'post', array('action' =>'#'));
        $token=$form->addElement('hidden', 'token', array('value' =>getToken()));
        $frame=$form->addElement('fieldset')->setLabel(_m('Редактирование альбома'));
        $name=$frame->addElement('text', 'name', null, array('label' => _m('Название:')));
        $descr=$frame->addElement(
            'textarea',
            'describe',
            array('class' =>'bbcode',  'rows' =>'10', 'cols' =>'50'),
            array('label' => _m('Описание:'))
        );
        $submit=$frame->addElement('submit', 'submit', array('value' =>_m('Сохранить')));

        $name->addRule(
            'required',
            _m('Название альбома не может быть пустым'),
            null,
            $this->validation
        );

        if ($album) {
            $form->addDataSource(
                Factory::create(
                    'HTML_QuickForm2_DataSource_Array',
                    $album->getData()
                )
            );
        }

        return $form;
    }
}
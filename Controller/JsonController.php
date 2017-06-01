<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Controller;

use LC\LabCMS;

class JsonController
{
    public $validation=1;
    //комментарии к альбомам имеют другой ключ
    protected $album_comments_type=2;
    protected $albumService;
    protected $imageService;


    public function __construct(
        \LCM\lc_album\Service\AlbumService $albumService,
        \LCM\lc_album\Service\ImageService $imageService
    )
    {
        $this->albumService=$albumService;
        $this->imageService=$imageService;
        $this->validation=\HTML_QuickForm2_Rule::SERVER | \HTML_QuickForm2_Rule::CLIENT;
    }

    public function defaultAction()
    {
        $this->showError(_m('Метод не найден'));
    }

    public function albumAction()
    {
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        if (isset($_GET['data_type']) && $_GET['data_type']=='xml') {
            return $this->albumXML($album, 1, 500);
        } else {
            return $this->albumJSON($album, 1, 500);
        }
    }
    
    protected function albumJSON($album, $page, $atPage)
    {
        $images=$this->imageService->getByAlbum($album, $page, $atPage, false);
        $result=array();
        foreach ($images['collection'] as $image) {
            $files=$this->imageService->getImageFiles($image);
            $result['images'][]=(object)array(
                'href'=>_url(array('action'=>'image', 'params'=>$image->id)),
                'image'=>$files['original'],
                'thumb'=>$files['small'],
                'title'=>$image->name?$image->name:''
            );
        }
        $result['album']=(object)array('name'=>$album->name);
        $this->showResult($result);
    }

    protected function albumXML($album, $page, $atPage)
    {
        $this->showError("Not implemented");
    }

    public function imageAddAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }

        $form=$this->imageService->buildUploadForm();

        if ($form->validate()) {
            $data=$form->getValue();
            $data['album_id']=$album->id;
            $image=$this->imageService->add($data);
            if ($image) {
                $this->albumService->addImage($album, $image);
                $files=$this->imageService->getImageFiles($image);
                $info=array(
                    'name' => $image->filename,
                    'url' =>_url(array('params' => $image->id, 'action' =>'image')),
                    'thumbnailUrl' => $files['small'],
                    'size' =>filesize(LAB_PATH_ROOT.$files['small']),
                    'status' =>1,
                    'width' =>100,
                    'height' =>100
                );
                $this->showResult($info);
            }
        }

        if (LabCMS::i()->errors) {
            $this->showError(LabCMS::i()->errors[0]);
        }
    }

    public function imageVoteAjaxAction()
    {
        die("not implemented");
    }

    protected function getAlbumById($id)
    {
        if (!$album=$this->albumService->getById($id)) {
            return $this->showError(_m('Альбом не найден'));
        }
        return $album;
    }

    protected function showResult($result)
    {
        echo json_encode($result);
        exit;
    }

    protected function showError($message)
    {
        $this->showResult(array('error' =>$message));
    }
}
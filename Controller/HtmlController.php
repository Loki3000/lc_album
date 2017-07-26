<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Controller;

use LC\Config;
use LC\Factory;

class HtmlController extends \LC\ModuleTools
{
    public $validation=1;
    //комментарии к альбомам имеют другой ключ
    protected $album_comments_type=2;
    protected $tpl;
    protected $albumService;
    protected $imageService;


    public function __construct(
        \LC\Tpl $tpl,
        \LCM\lc_album\Service\AlbumService $albumService,
        \LCM\lc_album\Service\ImageService $imageService
    ) {
        $this->tpl=$tpl;
        $this->albumService=$albumService;
        $this->imageService=$imageService;
        $this->validation=\HTML_QuickForm2_Rule::SERVER | \HTML_QuickForm2_Rule::CLIENT;
    }

    public function defaultAction()
    {
        return $this->listAction();
    }

    public function listAction()
    {
        $page=isset($_GET['var1'])?intval($_GET['var1']):1;

        $data=$this->albumService->getByPage($page, Config::get('albums_at_page'));

        $this->tpl->assign('pager', $this->getPager($data['totalCount'], $page, Config::get('albums_at_page')));
        $this->tpl->assign('albums', $data['collection']);

        $this->setTpl('albums');
    }

    //манипуляции с альбомом

    public function albumAction()
    {
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        $this->setTpl('album');

        $page=isset($_GET['var2'])?intval($_GET['var2']):1;
        $data=$this->imageService->getByAlbum($album, $page, Config::get('items_at_page'));
        $this->tpl->assign('images', $data['collection']);
        $this->tpl->assign('pager', $this->getPager($data['totalCount'], $page, Config::get('items_at_page')));
        $this->tpl->assign('album', $album);

        /*if (Config::get('use_album_comments')) {
            list($item, $comments, $votes)=$album->getComments($album);
            $this->tpl->assign('comments', $comments['collection']);
            $this->tpl->assign('comment_votes', $votes);
            $this->tpl->assign('comments_item', $item);
        }*/
        $this->setTitle($album);
    }

    public function albumAddAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }

        $form=$this->albumService->buildEditForm();

        if ($form->validate()) {
            if ($album=$this->albumService->add($form->getValue())) {
                refresh('', _url(array('params' =>array($album->id, 1), 'action' =>'album')));
            }
        }

        $this->tpl->assign('form', $this->renderForm($form));
        $this->setTpl('form');
        $this->setTitle(null, null, _m('Добавление альбома'));
    }

    public function albumEditAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }

        $form=$this->albumService->buildEditForm($album);

        if ($form->validate()) {
            $this->albumService->update($album, $form->getValue());
            refresh('', _url(array('params' => $album->id, 'action' =>'album')));
        }

        $this->tpl->assign('form', $this->renderForm($form));
        $this->setTpl('form');

        $this->setTitle($album, null, _m('Редактирование альбома'));
    }

    public function albumUpAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!checkToken($_GET['token'], false)) {
            return false;
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        $this->albumService->moveUp($album);
        //возвращаемся на ту же страницу списка альбомов
        refresh('', _url(array('params' => $_GET['var2'])));
    }

    public function albumDownAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!checkToken($_GET['token'], false)) {
            return false;
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        $this->albumService->moveDown($album);
        //возвращаемся на ту же страницу списка альбомов
        refresh('', _url(array('params' => $_GET['var2'])));
    }

    public function albumDeleteAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!checkToken($_GET['token'], false)) {
            return false;
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        $this->imageService->deleteByAlbum($album);
        $this->albumService->delete($album);
        refresh();
    }

    public function setLogoAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!checkToken($_GET['token'], false)) {
            return false;
        }
        if (!$image=$this->getImageById($_GET['var1'])) {
            return;
        }
        $album=$this->getAlbumById($image->album_id);
        if ($this->albumService->setLogo($album, $image)) {
            refresh('', _url(array('params' => $album->id, 'action' =>'album')));
        }
    }


    public function imageSortAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!$album=$this->getAlbumById($_GET['var1'])) {
            return;
        }
        $images=$this->imageService->getByAlbum($album, 1, 0);

        if ($_SERVER['REQUEST_METHOD']=='POST') {
            $this->imageService->sortImages($images['collection'], $_POST['sort']);
            //выбираем заново отсортированный альбом
            $images=$this->imageService->getByAlbum($album, 1, 0);
        }

        $this->tpl->assign('images', $images['collection']);
        $this->tpl->assign('album', $album);
        $this->setTpl('sorter');
        $this->setTitle($album, null, _m('Сортировка изображений'));
    }

    //манипуляции с изображением

    public function imageAction()
    {
        if (!$image=$this->getImageById($_GET['var1'])) {
            return;
        }
        $album=$this->getAlbumById($image->album_id);
        $this->tpl->assign('album', $album);
        $this->tpl->assign('image', $image);
        $this->tpl->assign('next', $this->imageService->getNext($image));
        $this->tpl->assign('prev', $this->imageService->getPrevious($image));
        $this->setTpl('image');

        /*
        if (Config::get('image_rating_enable')) {
            $voter=\LC\Rating_Generic::init();
            $this->tpl->assign('image_votes', $voter->getUserItemVotes($image->id, $image->vote_type));
        }

        /*if (Config::get('use_image_comments')) {
            list($item, $comments, $votes)=$image->getComments();
            $this->tpl->assign('comments', $comments['collection']);
            $this->tpl->assign('comment_votes', $votes);
            $this->tpl->assign('comments_item', $item);
        }*/
        $this->setTitle($album, $image);
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
            }
            refresh('', _url(array('params' => $image->id, 'action' =>'image')));
        }

        $this->tpl->assign('album', $album);
        $this->setTpl('upload');
        $this->tpl->assign('form', $this->renderForm($form));

        $this->setTitle($album, null, _m('Добавление изображения'));
    }

    public function imageEditAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!$image=$this->getImageById($_GET['var1'])) {
            return;
        }
        $form=$this->imageService->buildEditForm($image);
        $this->setAlbums(array($image));

        if ($form->validate()) {
            $data=$form->getValue();
            $old_album_id=$image->album_id;
            $this->imageService->update($image, $data);
            if (true || $data['album_id']!=$old_album_id) {
                $this->albumService->addImage($this->albumService->getById($image->album_id), $image);
            }

            if (!empty($data['next'])) {
                if ($next=$this->imageService->getNext($image)) {
                    $url=_url(array('params' => $next->id, 'action' =>'image_edit'));
                } else {
                    $url=_url(array('params' => $image->album_id, 'action' =>'album'));
                }
            } else {
                $url=_url(array('params' => $image->id, 'action' =>'image'));
            }
            if (!empty($url)) {
                refresh('', $url);
            }
        }

        $this->setTpl('form');
        $this->tpl->assign('image', $image);
        $this->tpl->assign('album', $image->getAlbum());
        $this->tpl->assign('form', $this->renderForm($form));

        $this->setTitle($image->getAlbum(), $image, _m('Редактирование изображения'));
    }

    public function imageDeleteAction()
    {
        if (!access('admin')) {
            return $this->error403();
        }
        if (!checkToken($_GET['token'], false)) {
            return false;
        }
        if (!$image=$this->getImageById($_GET['var1'])) {
            return;
        }
        if (!$next=$this->imageService->getNext($image)) {
            $next=$this->imageService->getPrevious($image);
        }

        /*$mObject=Factory::create('lc_comments.Api');
        $mObject->deleteItem($image->id);*/

        $this->imageService->delete($image);
        if ($next) {
            refresh('', _url(array('params' => $next->id, 'action' =>'image')));
        }
        $album=$this->getAlbumById($image->album_id);
        refresh('', _url(array('params' => $album->id, 'action' =>'album')));
    }

    //вспомогательные методы
    protected function getAlbumById($id)
    {
        if (!$album=$this->albumService->getById($id)) {
            return $this->error(_m('Альбом не найден'));
        }
        return $album;
    }

    protected function getImageById($id)
    {
        if (!$image=$this->imageService->getById($id)) {
            return $this->error(_m('Изображение не найдено'));
        }
        return $image;
    }

    protected function setAlbums($images)
    {
        $collection=$albums=array();
        foreach ($images as $image) {
            $albums[]=$image->album_id;
        }

        if ($albums) {
            $albums=$this->albumService->getByArrayId($albums);
        }

        foreach ($images as $image) {
            $image->setAlbum($albums[$image->album_id]);
        }
        return $images;
    }

    protected function setTitle($album, $image = null, $text = '', $url = '')
    {
        $title[]=Config::get('core.title');

        if ($album) {
            $title[]=$album->name;
            addBreadcrumb(
                $album->name,
                _url(array('params' => $album->id, 'action' =>'album'))
            );
        }

        if ($image) {
            $name=$image->name?$image->name:'***';
            $title[]=$name;
            addBreadcrumb(
                $name,
                _url(array('params' => $image->id, 'action' =>'image'))
            );
        }

        if ($text) {
            $title[]=$text;
            addBreadcrumb(
                $text,
                $url
            );
        }


        $title=array_reverse($title);
        $title=implode(' / ', $title);
        Config::set('core.title', $title);
    }
}

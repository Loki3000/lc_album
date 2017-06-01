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
    protected $imageView;
    protected $albumView;


    public function __construct(\LC\Tpl $tpl,
        \LCM\lc_album\Service\AlbumService $albumService,
        \LCM\lc_album\Service\ImageService $imageService,
        \LCM\lc_album\View\AlbumView $albumView,
        \LCM\lc_album\View\ImageView $imageView)
    {
        $this->tpl=$tpl;
        $this->albumService=$albumService;
        $this->imageService=$imageService;
        $this->albumView=$albumView;
        $this->imageView=$imageView;
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
        $this->tpl->assign('albums', $this->getAlbumsViewCollection($data['collection']));

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
        $this->tpl->assign('images', $this->getImagesViewCollection($data['collection']));
        $this->tpl->assign('pager', $this->getPager($data['totalCount'], $page, Config::get('items_at_page')));
        $this->tpl->assign('album', $this->getAlbumView($album));

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
            if ($album=$this->albumService->create($form->getValue())) {
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

        $this->tpl->assign('images', $this->getImagesViewCollection($images['collection']));
        $this->tpl->assign('album', $this->getAlbumView($album));
        $this->setTpl('sorter');
        $this->setTitle($album, null, _m('Сортировка изображений'));
    }

    //манипуляции с изображением

    public function imageAction()
    {
        if (!$image=$this->getImageById($_GET['var1'])) {
            return;
        }
        $imageView=$this->getImageView($image);
        $this->tpl->assign('album', $imageView->getAlbum());
        $this->tpl->assign('image', $imageView);
        $this->tpl->assign('next', $this->getImageView($this->imageService->getNext($image)));
        $this->tpl->assign('prev', $this->getImageView($this->imageService->getPrevious($image)));
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
        $this->setTitle($imageView->getAlbum(), $imageView);
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
        $imageView=$this->getImageView($image);
        $this->tpl->assign('image', $imageView);
        $this->tpl->assign('album', $imageView->getAlbum());
        $this->tpl->assign('form', $this->renderForm($form));

        $this->setTitle($imageView->getAlbum(), $image, _m('Редактирование изображения'));
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

    protected function getAlbumView($item)
    {
        if(!$item) return;
        $data=$this->getAlbumsViewCollection(array($item));
        return array_pop($data);
    }

    protected function getImageView($item)
    {
        if(!$item) return;
        $data=$this->getImagesViewCollection(array($item));
        return array_pop($data);
    }

    protected function getAlbumsViewCollection($items)
    {
        $collection=array();
        foreach ($items as $item) {
            $album=new $this->albumView($item);
            $album->setLogo($this->albumService->getLogoUrl($item));
            $collection[$album->id]=$album;
        }
        return $collection;
    }

    protected function getImagesViewCollection($items)
    {
        $collection=$albums=array();
        foreach ($items as $item) {
            $albums[]=$item->album_id;
        }

        if ($albums) {
            $albums=$this->getAlbumsViewCollection($this->albumService->getByArrayId($albums));
        }

        foreach ($items as $item) {
            $image=new $this->imageView($item);
            $image->setFiles($this->imageService->getImageFiles($item));
            $image->setAlbum($albums[$image->album_id]);
            $collection[$image->id]=$image;
        }
        return $collection;
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
<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

namespace LCM\lc_album\Repository;

use \LC\Config;
use \LC\Factory;

class ImageRepository extends Repository
{
    protected $table='lc_album_images';

    protected $allowedFields=array(
        'id', 'album_id', 'name', 'describe', 'filename', 'pos',
        'updated', 'total_votes', 'vote_result', 'rating'
    );

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher, \LCM\lc_album\Entity\ImageEntity $entity)
    {
        parent::__construct($orm, $cacher, $entity);
    }

    public function getByAlbum($album, $page, $atPage, $count = true)
    {
        $key=$this->getCacheKey().$page.'_'.$atPage.'_'.$count;
        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table)->where('album_id', $album->id);

            if ($count) {
                $data['totalCount']=$table->count('*');
            }

            if ($atPage) {
                $table->paged($atPage, $page);
            }

            $data['collection']=$table->orderBy('pos')->fetchAll();
            $data['collection']=$this->prepareResults($data['collection']);

            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        return $data;
    }

    public function add($data)
    {
        if ($data['Filedata']) {
            $data['filename']=$this->uploadImage($data['Filedata']);
        }
        $image=$this->orm->createRow($this->table, $this->getFilteredFields($data));
        $image->save();
        $entity=$this->prepareResult($image);
        $entity->pos=$entity->id;
        $this->save($entity);
        return $entity;
    }

    public function delete($item)
    {
        $this->deleteFiles($item);
        return parent::delete($item);
    }

    public function save($entity)
    {
        $entity->files=$this->getFiles($entity);
        return parent::save($entity);
    }

    public function getPrevious($image)
    {
        $item=$this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->where('pos < :pos', [':pos'=>$image->pos])
            ->orderBy('pos', 'DESC')
            ->limit(1)
            ->fetch();
        return $this->prepareResult($item);
    }

    public function getNext($image)
    {
        $item=$this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->where('pos > :pos', [':pos'=>$image->pos])
            ->orderBy('pos')
            ->limit(1)
            ->fetch();
        return $this->prepareResult($item);
    }

    public function getMaxPos($image)
    {
        return $this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->max('pos');
    }

    protected function prepareResults($collection)
    {
        $result=array();
                
        foreach ($collection as $item) {
            $result[$item->id]=new $this->entity($item->getData());
            $result[$item->id]->setFiles($this->getFiles($item));
        }
        return $result;
    }

    protected function getFiles($image)
    {
        $files=array();
        foreach (Config::get('tn') as $key => $item) {
            $files[$key]=Config::get('basepath').$item['path'].'/'.$image->filename;
        }
        return $files;
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
        foreach ($image->files as $file) {
            unlink(LAB_PATH_ROOT.$file);
        }
    }
}

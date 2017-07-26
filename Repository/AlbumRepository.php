<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

namespace LCM\lc_album\Repository;

use \LC\Config;

class AlbumRepository extends Repository
{
    protected $table='lc_album_albums';

    protected $allowedFields=array('id', 'name', 'describe', 'logo', 'pos');

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher, \LCM\lc_album\Entity\AlbumEntity $entity)
    {
        $this->orm=$orm;
        $this->cacher=$cacher;
        $this->entity=$entity;

        $this->orm->setRequired($this->table, 'name');
    }

    public function add($data)
    {
        $album=$this->orm->createRow($this->table, $this->getFilteredFields($data));
        $album->save();
        $entity=$this->prepareResult($album);
        $entity->pos=$entity->id;
        $this->save($entity);
        return $entity;
    }

    public function delete($item)
    {
        $this->deleteFiles($item);
        return parent::delete($item);
    }

    public function getByPage($page, $atPage, $count = true)
    {
        $key=$this->getCacheKey().$page.'_'.$atPage.'_'.$count;

        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table);

            if ($count) {
                $data['totalCount']=$table->count('*');
            }

            $data['collection']=$table->orderBy('pos')->paged($atPage, $page)->fetchAll();
            $data['collection']=$this->prepareResults($data['collection']);

            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        
        return $data;
    }

    public function getPrevious($album)
    {
        $item=$this->orm->table($this->table)
            ->where('pos < :pos', [':pos'=>$album->pos])
            ->orderBy('pos', 'DESC')
            ->limit(1)
            ->fetch();
        return $this->prepareResult($item);
    }

    public function getNext($album)
    {
        $item=$this->orm->table($this->table)
            ->where('pos > :pos', [':pos'=>$album->pos])
            ->orderBy('pos')
            ->limit(1)
            ->fetch();
        return $this->prepareResult($item);
    }

    protected function prepareResults($collection)
    {
        $result=array();
                
        foreach ($collection as $item) {
            $result[$item->id]=new $this->entity($item->getData());
            $result[$item->id]->setLogo($this->getLogoUrl($item));
        }
        return $result;
    }

    public function setLogo($album, $file)
    {
        $dir=LAB_PATH_ROOT.Config::get('basepath').Config::get('logo_path');
        if (!is_dir($dir) && !mkdir($dir, 0777)) {
            return LabCMS::i()->error(sprintf(_m("Не могу создать директорию %s"), $dir));
        }
        $album->logo=basename($file);
        $this->save($album);
        copy(LAB_PATH_ROOT.$file, $dir.'/'.$album->logo);
        return true;
    }

    protected function getLogoUrl($album)
    {
        if (!$album->logo) {
            return '';
        }

        return Config::get('basepath').Config::get('logo_path').'/'.$album->logo;
    }

    public function deleteFiles($album)
    {
        unlink(LAB_PATH_ROOT.$album->logo_file);
    }

    public function getAllAsArray()
    {
        $result=array();
        $all=$this->getByPage(1, 500);
        foreach ($all['collection'] as $album) {
            $result[$album->id]=$album->name;
        }
        return $result;
    }
}

<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

namespace LCM\lc_album\Repository;

class AlbumRepository extends Repository
{
    protected $table='lc_album_albums';

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher)
    {
        $this->orm=$orm;
        $this->cacher=$cacher;

        $this->orm->setRequired( $this->table, 'name' );
    }

    public function add($data)
    {
        $album=$this->orm->createRow($this->table, $data);
        $album->save();
        return $album;
    }

    public function getByPage($page, $atPage, $count=true)
    {
        $key=$this->getCacheKey().$page.'_'.$atPage.'_'.$count;

        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table);

            if ($count) $data['totalCount']=$table->count('*');
            $data['collection']=$table->orderBy('pos')->paged($atPage, $page)->fetchAll();

            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        
        return $data;
    }

    public function getByArrayId($ids)
    {
        $key=$this->getCacheKey().implode('_', $ids);

        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table);

            $result=$table->where('id', $ids)->fetchAll();
            while ($item=array_pop($result))
            {
                $data[$item->id]=$item;
            }
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
        return $item;
    }

    public function getNext($album)
    {
        $item=$this->orm->table($this->table)
            ->where('pos > :pos', [':pos'=>$album->pos])
            ->orderBy('pos')
            ->limit(1)
            ->fetch();
        return $item;
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
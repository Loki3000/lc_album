<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Repository;

class Repository
{
    protected $orm;
    protected $cacher;

    protected $cache_time=604800;//60*60*24*7

    protected $table;

    protected $allowedFields=array();

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher)
    {
        $this->orm=$orm;
        $this->cacher=$cacher;
    }

    public function getById($id)
    {
        $key=$this->getCacheKey().$id;
        $data=null;

        if (false === ($data=$this->cacher->get($key))) {
            $data=$this->orm->table($this->table, $id);

            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        
        return $data;
    }

    public function delete($item)
    {
        return $this->deleteByArrayId(array($item->id));
    }

    public function deleteByArrayId($ids)
    {
        $result=$this->orm->table($this->table)->where('id', $ids)->delete();
        foreach ($ids as $id) {
            $this->cacher->remove($this->getCacheKey().$id);
        }
        $this->cacher->clean('MATCH_TAG', array($this->getCacheKeyUpdate()));
        return $result;
    }

    protected function getCacheTime()
    {
        return $this->cache_time;
    }
    protected function getCacheKey()
    {
        return $this->table.'_';
    }

    protected function getCacheKeyUpdate()
    {
        return $this->getCacheKey().'update';
    }

    public function save($item)
    {
        $item->save();
    }
    
    public function update($item, $data)
    {
        $item->update($this->getFilteredFields($data));
        return $item;
    }

    public function create($data)
    {
        $item=$this->orm->createRow($this->table, $this->getFilteredFields($data));
        return $item;
    }

    public function getFilteredFields($data)
    {
        foreach ($data as $k=>$v) {
            if (!in_array($k, $this->allowedFields)) {
                unset($data[$k]);
            }
        }
        return $data;
    }
}
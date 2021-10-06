<?php

namespace Makiavelo\Flex\Traits;

use Makiavelo\Flex\Flex;

/**
 * Trait to add record versions
 */
trait Versionable
{
    public $version;

    /**
     * Initialize the versions relationship
     * 
     * @param array $params
     * 
     * @return void
     */
    public function _translatableInit($params = [])
    {
        $this->meta()->set('protected->traits->versionable', $params);
        $this->relations()->add([
            'name' => 'Versions',
            'key' => $this->meta()->get('table') . '_id',
            'table' => $this->meta()->get('table') . '_version',
            'class' => 'Makiavelo\\Flex\\Flex',
            'remove_orphans' => true,
            'type' => 'Has'
        ]);
        $this->meta()->add('fields', [
            'version' => ['type' => 'INT', 'nullable' => false]
        ]);
    }

    /**
     * Create a copy, update the version number.
     * 
     * @return boolean
     */
    public function _versionablePreSave()
    {
        if (!$this->version) $this->version = 0;

        $this->version++;
        $this->version = (string) $this->version;
        $model = new Flex();
        $model->meta()->add('table', $this->meta()->get('table') . '_version');
        $model->id = '';
        $attrs = $this->getAttributes();

        foreach ($attrs as $field => $value) {
            $model->$field = $value;
        }

        $this->versions()->add($model);
        return true;
    }

    /**
     * Switch to another version.
     * 
     * The current version number is kept, so the overall versions are always
     * incremented.
     * 
     * @param mixed $num
     * 
     * @return void
     */
    public function changeVersion($num)
    {
        $versions = $this->versions()->with(['version' => (string) $num])->fetch();
        if ($versions) {
            $version = $versions[0];
            $attrs = $version->getAttributes();
            $idKey = $this->meta()->get('table') . '_id';

            foreach ($attrs as $field => $value) {
                if ($field !== $idKey && $field !== 'version') {
                    $this->$field = $value;
                }
            }
        }
    }
}

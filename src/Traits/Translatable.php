<?php

namespace Makiavelo\Flex\Traits;

use Makiavelo\Flex\Flex;

/**
 * Trait to add translations to fields
 */
trait Translatable
{
    public $locale;
    public $availableLocales;

    /**
     * Initialize the translatable relationship
     * 
     * @param array $params
     * 
     * @return void
     */
    public function _translatableInit($params = [])
    {
        $this->meta()->set('protected->traits->translatable', $params);
        $this->relations()->add([
            'name' => 'Translations',
            'key' => $this->meta()->get('table') . '_id',
            'table' => $this->meta()->get('table') . '_translation',
            'class' => 'Makiavelo\\Flex\\Flex',
            'remove_orphans' => true,
            'type' => 'Has'
        ]);
    }

    /**
     * Create a translation with the fields defined in the $values parameter.
     * 
     * @param mixed $locale
     * @param mixed $values
     * 
     * @return Flex
     */
    public function translation($locale, $values)
    {
        $current = $this->translations()->with(['locale' => $locale])->fetch();
        if ($current) {
            $model = $current[0];
        } else {
            $model = new Flex();
            $model->meta()->add('table', $this->meta()->get('table') . '_translation');
            $model->id = '';
            $model->locale = $locale;
        }

        foreach ($values as $field => $value) {
            $model->$field = $value;
        }

        return $model;
    }
}

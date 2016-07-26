<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field_One
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;

    /**
     * Use this alias for related entity by default.
     *
     * @var string
     */
    protected $table_alias;

    /**
     * What should we pass into owner->ref() to get
     * through to this reference.
     *
     * @var string
     */
    protected $link;

    /**
     * Definition of the destination model, that can
     * be either an object, a callback or a string.
     *
     * @var Model|null
     */
    protected $model;

    /**
     * Our field will be 'id' by default.
     *
     * @var string
     */
    protected $our_field = null;

    /**
     * Their field will be $table.'_id' by default.
     *
     * @var string
     */
    protected $their_field = null;

    /**
     * Points to the join if we are part of one.
     *
     * @var Join|null
     */
    protected $join = null;

    protected $default = null;

    /**
     * Default constructor. Will copy argument into properties.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        if (isset($defaults[0])) {
            $this->link = $defaults[0];
            unset($defaults[0]);
        }

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     *
     * @return string
     */
    public function getDesiredName()
    {
        return '#ref_'.$this->link;
    }

    /**
     * Initialization.
     */
    public function init()
    {
        $this->_init();
        if (!$this->our_field) {
            $this->our_field = $this->link;
        }
        if (!$this->owner->hasElement($this->our_field)) {
            $this->owner->addField($this->our_field, ['system' => true, 'join' => $this->join, 'default'=>$this->default]);
        }
    }

    /**
     * Returns model of field.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function getModel($defaults = [])
    {
        if (!isset($defaults['table_alias'])) {
            if (!$this->table_alias) {
                $this->table_alias = $this->link;
                $this->table_alias = preg_replace('/_id/', '', $this->table_alias);
                $this->table_alias = preg_replace('/([a-zA-Z])[a-zA-Z]*[^a-zA-Z]*/', '\1', $this->table_alias);
                if(isset($this->owner->table_alias)) {
                    $this->table_alias = $this->owner->table_alias.'_'.$this->table_alias;
                }
            }
            $defaults['table_alias'] = $this->table_alias;
        }
        if (is_object($this->model) && $this->model instanceof \Closure) {
            $c = $this->model;

            $c = $c($this->owner, $this);
            if (!$c->persistence) {
                $c = $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        if (is_object($this->model)) {
            $c = clone $this->model;
            if (!$this->model->persistence && $this->owner->persistence) {
                $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        // last effort - try to add model
        $p = $this->owner->persistence;

        if (is_array($this->model)) {
            $model = $this->model[0];
            $md = $this->model;
            unset($md[0]);

            $defaults = array_merge($md, $defaults);
        } else {
            $model = $this->model;
        }

        return $p->add($p->normalizeClassName($model, 'Model'), $defaults);
    }

    /**
     * Returns our field or id field.
     *
     * @return Field
     */
    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getElement($this->our_field);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        $m = $this->getModel($defaults);
        if ($this->owner->loaded()) {
            if ($this->their_field) {
                return $m->tryLoadBy($this->their_field, $this->owner[$this->our_field])
                    ->addHook('afterSave', function ($m) {
                        $this->owner[$this->our_field] = $m[$this->their_field];
                    })
                    ->addHook('afterDelete', function ($m) {
                        $this->owner[$this->our_field] = null;
                    });
            } else {
                return $m->tryLoad($this->owner[$this->our_field])
                    ->addHook('afterSave', function ($m) {
                        $this->owner[$this->our_field] = $m->id;
                    })
                    ->addHook('afterDelete', function ($m) {
                        $this->owner[$this->our_field] = null;
                    });
            }
        } else {
            $m = clone $m; // we will be adding conditions!

            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
        }
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $arr = [
            'ref'     => $this->link,
            'model'   => $this->model,
        ];

        if ($this->our_field) {
            $arr['our_field'] = $this->our_field;
        }

        if ($this->their_field) {
            $arr['their_field'] = $this->their_field;
        }

        return $arr;
    }

    // }}}
}

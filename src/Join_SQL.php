<?php

namespace atk4\data;

class Join_SQL extends Join {

    protected $foreign_alias;
    /**
     * A short symbolic name that will be used as an alias for the joined table
     */

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind'=>'right']
     * as second argument to join().
     */
    protected $kind;

    /**
     * By default we create ON expresison ourselves, but if you want to specify
     * it, use the 'on' property.
     */
    protected $on = null;

    /**
     * Query we are building
     */
    protected $dsql = null;

    /**
     * Will use either foreign_alias or create #join_<table> 
     */
    public function getDesiredName()
    {
        return '_'.($this->foreign_alias ?: $this->foreign_table[0]);
    }

    /**
     * This method is to figure out stuff
     */
    function init()
    {
        parent::init();

        $this->dsql = $this->owner->persistence->initQuery($this->owner);
        $this->dsql->reset('table');

        $this->owner->persistence_data['use_table_prefixes']=true;

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak?'left':'inner';
        }

        // Our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = $this->short_name;
        }

        $this->dsql->table($this->foreign_table, $this->foreign_alias);

        $this->owner->addhook('initSelectQuery', $this);

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->addHook('afterInsert', $this, null, -5);
            $this->owner->addHook('beforeUpdateQuery', $this, null, -5);
            $this->owner->addHook('beforeDelete', [$this, 'doDelete'], null, -5);
        } else {
            $this->owner->addHook('beforeInsertQuery', $this);
            $this->owner->addHook('beforeUpdateQuery', $this);
            $this->owner->addHook('afterDelete', [$this, 'doDelete']);
            $this->owner->addHook('afterLoad', $this);
        }
    }

    function dsql()
    {
        return clone $this->dsql;
    }


    /**
     * Before query is executed, this method will be called. 
     */
    function initSelectQuery($model, $query)
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->join(
                $this->foreign_table.' '.$this->foreign_alias,
                $this->on instanceof \atk4\dsql\Expression ?
                $this->on :
                $query->expr($this->on)
            );
            return;
        }

        $query->join(
            $this->foreign_table.'.'.$this->foreign_field.(
                isset($this->foreign_alias)?(' '.$this->foreign_alias):''
            ),
            (
                isset($this->owner->table_alias) ? 
                $this->owner->table_alias: 
                ($this->owner->table).'.'.$this->master_field)
        );

        if ($this->reverse) {
            $query->field([$this->short_name=>($this->join?:($this->owner->table.'.'.$this->master_field))]);
        } else {


            $query->field([$this->short_name=>$this->foreign_alias.'.'.$this->foreign_field]);
        }
    }

    function afterLoad($model)
    {
        // we need to collect ID
        $this->id = $model->data[$this->short_name];
        unset($model->data[$this->short_name]);
    }

    function afterUnload($model)
    {
        $this->id = null;
    }


    function beforeInsertQuery($model, $query)
    {
        if ($this->weak) {
            return;
        }

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasElement($this->master_field) && $model[$this->master_field]) {
            return;
        }

        $insert = $this->dsql()->set($this->foreign_field, null);
        $insert->insert();
        $this->id = $insert->connection->lastInsertID();

        if (isset($this->join)) {
            $query = $this->join->dsql;
        }

        $query->set($this->master_field, $this->id);
    }

    function afterInsert($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $insert = $this->dsql();
        $insert
            ->set(
                $this->foreign_field, 
                isset($this->join) ? $this->join->id : $id
            );
        $insert->insert();
        $this->id = $insert->connection->lastInsertID();
    }

    function beforeUpdateQuery($model, $query)
    {
        if ($this->weak) {
            return;
        }

        //if ($this->dsql->args['set']) {
        $update = $this->dsql();
        $update->where($this->foreign_field, $this->id);
        $update->update();
        //}
    }

    function doDelete($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $delete = $this->dsql();
        $delete
            ->reset('table')
            ->table($this->foreign_table)
            // TODO: remove 2 lines above when DSQL fixes it's delete template
            // https://github.com/atk4/dsql/commit/32e9e0ceee2c7032d2f7012f612f8718c12e9d10
            ->where($this->foreign_field, $this->id)
            ;

        //if ($this->delete_behaivour == 'cascade') {
            $delete->delete();
        //} elseif ($this->delete_behaivour == 'setnull') {
            //$delete
                //->set($this->foreign_field, null)
                //->update();
        //}
    }

    function set($field, $value)
    {
        $this->dsql->set($field, $value);
    }


}

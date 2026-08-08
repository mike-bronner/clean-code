<?php

class User extends Model
{
    protected $with = ['profile'];
}

class Admin extends Authenticatable
{
    protected $with = ['roles', 'permissions'];
}

class RoleUser extends Pivot
{
    protected $with = ['role'];
}

class Order extends BaseModel
{
    protected $with = array('lines');
}

class Invoice extends \Illuminate\Database\Eloquent\Relations\Pivot
{
    protected $with = ['customer'];
}

class Ledger extends model
{
    private $with = ['entries', 'entries.tax'];
}

class Shipment extends Model
{
    public $with = ['carrier'];
}

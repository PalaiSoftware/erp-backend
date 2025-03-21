<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UidCid extends Model
{
    protected $table = 'uid_cid_table'; // Matches your migration
    protected $primaryKey = 'uid';      // Set uid as the primary key
    public $incrementing = false;       // uid is not auto-incrementing
    protected $fillable = ['uid', 'cid']; // Allows mass assignment
    public $timestamps = false;         // No created_at/updated_at columns
}
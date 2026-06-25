<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

       //модель при создании связана с таблицей 'orders' установим защиту дополнительно в модели
       protected $table = 'users';
       //снимем защиту для возможности записи атрубутов модели в БД
       protected $guarded = []; // ... или false

       // Константы ролей
        const ROLE_ADMIN = 'admin';
        const ROLE_DISPATCHER = 'dispatcher';
        const ROLE_DRIVER = 'driver';
        const ROLE_EXCAVATOR_OPERATOR = 'excavator_operator';
    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
//метод связывает объект класса User к Set через промежуточную табл. "set_users" затем указывается id-поле этого класса 'users_id'  потом поле к которму привязывается 'set_id'
    public function sets(){
       
        return $this->belongsToMany(Set::class, 'set_users', 'user_id', 'set_id');
    }
        /**
     * Грузовики водителя
     */
    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'driver_id');
    }

    /**
     * Экскаватор, к которому привязан оператор
     */
    public function miner(): BelongsTo
    {
        return $this->belongsTo(Miner::class);
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'role',
        'email',
        'password',
        'miner_id',
        'position'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDispatcher(): bool
    {
        return $this->role === self::ROLE_DISPATCHER;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isExcavatorOperator(): bool
    {
        return $this->role === self::ROLE_EXCAVATOR_OPERATOR;
    }
}

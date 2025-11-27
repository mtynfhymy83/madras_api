<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'ci_users';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'eitaa_id',
        'username',
        'name',
        'family',
        'displayname',
        'gender',
        'age',
        'email',
        'tel',
        'national_code',
        'birthday',
        'city',
        'state',
        'country',
        'postal_code',
        'address',
        'password',
        'avatar',
        'cover',
        'register',
        'active',
        'approved',
        'pending_reason',
        'level',
        'type',
        'last_seen',
        'code',
        'sendtime',
        'mobilechanged',
        'support',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'last_seen' => 'datetime',
            'date' => 'datetime',
            'active' => 'boolean',
            'approved' => 'boolean',
            'support' => 'boolean',
            'gender' => 'integer',
            'age' => 'integer',
            'code' => 'integer',
            'sendtime' => 'integer',
            'mobilechanged' => 'integer',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'username' => $this->username,
            'level' => $this->level,
            'active' => $this->active,
            'approved' => $this->approved,
        ];
    }

    /**
     * Relationships
     */
    public function meta(): HasMany
    {
        return $this->hasMany(UserMeta::class, 'user_id');
    }

    public function levelData(): HasMany
    {
        return $this->hasMany(UserLevel::class, 'level_id', 'level');
    }

    public function online(): HasOne
    {
        return $this->hasOne(Online::class, 'user_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(UserBook::class, 'user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'user_id');
    }

    /**
     * Authentication Methods
     */
    public static function authenticate(string $username, string $password): ?self
    {
        // Try username first
        $user = static::where('username', $username)->first();
        
        // If not found, try email
        if (!$user && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user = static::where('email', $username)->first();
        }

        if (!$user) {
            return null;
        }

        // Check password - handle both hashed and plain text (for migration)
        $passwordValid = false;
        if (Hash::check($password, $user->password)) {
            $passwordValid = true;
        } elseif (md5($password) === $user->password) {
            // Legacy password support - update to hashed
            $user->password = Hash::make($password);
            $user->save();
            $passwordValid = true;
        }

        if (!$passwordValid) {
            return null;
        }

        if (!$user->active || !$user->approved) {
            return null;
        }

        $user->updateLastSeen();
        return $user;
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen' => now()]);
    }

    /**
     * Authorization Methods
     */
    public function isAdmin(): bool
    {
        return $this->level === 'admin';
    }

    public function isEditor(): bool
    {
        return !empty($this->level) && $this->level !== 'user';
    }

    /**
     * Check if user has a specific permission
     * 
     * @param string $permission Permission key (without underscore prefix)
     * @param int|null $userId Optional user ID to check permissions for
     * @return bool
     */
    public function hasPermission(string $permission, ?int $userId = null): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($this->level === 'user') {
            return false;
        }

        $permissionKey = '_' . $permission;
        $levelId = $userId ? static::find($userId)?->level : $this->level;

        if (!$levelId) {
            return false;
        }

        $levelData = UserLevel::where('level_id', $levelId)
            ->where('level_key', $permissionKey)
            ->first();

        return $levelData && $levelData->level_value == '1';
    }

    public function checkAccess(?string $access = null): bool
    {
        if (!$this->active || !$this->level) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $levelExists = UserLevel::where('level_id', $this->level)->exists();

        if (!$levelExists) {
            return false;
        }

        if ($access) {
            return $this->hasPermission($access);
        }

        return true;
    }

    /**
     * User Level Methods
     */
    public function getUserLevel(?int $userId = null): ?string
    {
        if ($userId) {
            $user = static::find($userId);
            return $user?->level;
        }

        return $this->level;
    }

    public function getLevelName(?int $id = null, ?string $level = null): string
    {
        if ($id) {
            $levelData = UserLevel::where('level_id', $id)
                ->where('level_key', 'level_name')
                ->first();
            return $levelData?->level_value ?? '';
        }

        $level = $level ?: $this->getUserLevel();

        if (!$level) {
            return '';
        }

        // Check if it's a standard level
        $standardLevels = [
            'admin' => 'مدیر کل',
            'user' => 'کاربر',
            'operator' => 'اپراتور',
            'teacher' => 'استاد',
        ];

        if (isset($standardLevels[$level])) {
            return $standardLevels[$level];
        }

        // Try to get from user_level table
        $levelData = UserLevel::where('level_id', $level)
            ->where('level_key', 'level_name')
            ->first();

        return $levelData?->level_value ?? $level;
    }

    /**
     * Avatar and Cover Methods
     */
    public function getAvatarSrc(?int $userId = null, int $size = 150, ?string $src = null): string
    {
        return $this->getSrc($userId, $size, $src, 'avatar');
    }

    public function getCoverSrc(?int $userId = null, int $size = 300, ?string $src = null): string
    {
        return $this->getSrc($userId, $size, $src, 'cover');
    }

    protected function getSrc(?int $userId = null, int $size = 150, ?string $src = null, string $case = 'avatar'): string
    {
        if ($case !== 'avatar' && $case !== 'cover') {
            return '';
        }

        $defaultSrc = config("app.default_user_{$case}", "images/default-{$case}.jpg");

        if (!$userId && !$src) {
            $src = $defaultSrc;
        } elseif (!$src) {
            if ($userId) {
                $user = static::find($userId);
                $src = $user?->$case ?? $defaultSrc;
            } else {
                $src = $this->$case ?? $defaultSrc;
            }
        }

        if (filter_var($src, FILTER_VALIDATE_URL)) {
            return $src;
        }

        if (!file_exists(public_path($src))) {
            $src = $defaultSrc;
        }

        if ($size !== 'lg' && function_exists('thumb')) {
            $src = thumb($src, $size);
        }

        return asset($src);
    }

    /**
     * User Meta Methods
     */
    public function addMeta(array $data, ?int $userId = null): void
    {
        $userId = $userId ?: $this->id;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    UserMeta::create([
                        'user_id' => $userId,
                        'meta_name' => $key,
                        'meta_value' => $v,
                    ]);
                }
                $value = json_encode($value);
                $key .= '_json';
            }

            UserMeta::create([
                'user_id' => $userId,
                'meta_name' => $key,
                'meta_value' => $value,
            ]);
        }
    }

    public function updateMeta(array $data, ?int $userId = null): void
    {
        $userId = $userId ?: $this->id;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                UserMeta::where('user_id', $userId)
                    ->where('meta_name', $key . '_json')
                    ->delete();
            }

            UserMeta::where('user_id', $userId)
                ->where('meta_name', $key)
                ->delete();
        }

        $this->addMeta($data, $userId);
    }

    public function getMeta(?int $userId = null): object
    {
        $userId = $userId ?: $this->id;

        $meta = new \stdClass();
        $data = UserMeta::where('user_id', $userId)->get();

        foreach ($data as $item) {
            if (isset($meta->{$item->meta_name})) {
                if (!is_array($meta->{$item->meta_name})) {
                    $meta->{$item->meta_name} = [$meta->{$item->meta_name}];
                }
                $meta->{$item->meta_name}[] = $item->meta_value;
            } else {
                $meta->{$item->meta_name} = $item->meta_value;
            }
        }

        return $meta;
    }

    public static function getMetaValue(object $meta, string $metaName): ?string
    {
        return $meta->$metaName ?? null;
    }

    /**
     * Online Status Methods
     */
    public function isOnline(?int $id = null): bool
    {
        $id = $id ?: $this->id;
        return Online::where('user_id', $id)->exists();
    }

    public static function areOnline(array $ids): array
    {
        $onlines = Online::whereIn('user_id', $ids)->pluck('user_id')->toArray();
        $users = static::whereIn('id', $ids)->get(['id', 'last_seen']);

        $result = [];
        foreach ($users as $user) {
            $result[$user->id] = in_array($user->id, $onlines) ? true : $user->last_seen;
        }
		
		return $result;
	}

    public static function getOnlines(): array
    {
        $allOnlines = Online::count();
        $gOnline = Online::where('user_id', 0)->count();

        $usersOnline = Online::where('user_id', '!=', 0)
            ->with('user:id,username,displayname')
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();

        return [
            'all' => $allOnlines,
            'g_online' => $gOnline,
            'users_online_num' => $usersOnline->count(),
            'users_online' => $usersOnline->map(function ($online) {
                return [
                    'user_id' => $online->user_id,
                    'username' => $online->user->username ?? '',
                    'displayname' => $online->user->displayname ?? '',
                ];
            })->toArray(),
        ];
    }

    /**
     * User Statistics
     */
    public static function getRegisteredUsersInfo(): array
    {
        $allUsers = static::count();
        $doneUsers = static::where('register', 'done')->count();
        $pendingUsers = static::where('register', '!=', 'done')->count();

        return [
            'all' => $allUsers,
            'done' => $doneUsers,
            'pending' => $pendingUsers,
        ];
    }

    /**
     * Level Management Methods
     */
    public static function addLevel(int $id, array $data): bool
    {
        foreach ($data as $levelKey => $levelValue) {
            UserLevel::create([
                'level_id' => $id,
                'level_key' => $levelKey,
                'level_value' => $levelValue,
            ]);
        }

        return true;
    }

    public static function updateLevel(int $id, array $data): bool
    {
        $existingKeys = UserLevel::where('level_id', $id)->pluck('level_key')->toArray();

        foreach ($data as $levelKey => $levelValue) {
            if (in_array($levelKey, $existingKeys)) {
                UserLevel::where('level_id', $id)
                    ->where('level_key', $levelKey)
                    ->update(['level_value' => $levelValue]);
            } else {
                UserLevel::create([
                    'level_id' => $id,
                    'level_key' => $levelKey,
                    'level_value' => $levelValue,
                ]);
            }
        }

        return true;
    }

    public static function deleteLevel(int $id, string $replace): bool
    {
        static::where('level', $id)->update(['level' => $replace]);
        UserLevel::where('level_id', $id)->delete();

        return true;
    }

    /**
     * Account Management
     */
    public function deleteAccount(): bool
    {
        if ($this->id == 1) {
            return false;
        }

        $username = trim($this->username);

        // Delete related data
        \DB::table('ci_comments')
            ->where('table', 'users')
            ->where('row_id', $this->id)
            ->delete();

        \DB::table('ci_rates')
            ->where('table', 'users')
            ->where('row_id', $this->id)
            ->delete();

        // Delete user files
        if ($username && !in_array($username, ['_ac', '_df'])) {
            $paths = [
                public_path("uploads/{$username}"),
                public_path("uploads/_ac/{$username}"),
            ];

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    Storage::deleteDirectory($path);
                }
            }
        }

        return $this->delete();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}

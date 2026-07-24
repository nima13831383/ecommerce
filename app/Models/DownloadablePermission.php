<?php
// app/Models/DownloadablePermission.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadablePermission extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'product_id',
        'download_key',
        'file_path',
        'downloads_remaining',
        'download_count',
        'access_expires_at',
    ];

    protected $casts = [
        'downloads_remaining' => 'integer',
        'download_count'      => 'integer',
        'access_expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // آیا هنوز قابل دانلود است؟ (null = نامحدود)
    public function isDownloadable(): bool
    {
        if ($this->access_expires_at && $this->access_expires_at->isPast()) {
            return false;
        }

        return $this->downloads_remaining === null || $this->downloads_remaining > 0;
    }
}

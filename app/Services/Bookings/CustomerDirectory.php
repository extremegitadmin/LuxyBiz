<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use App\Support\SafeAudit;
use Illuminate\Support\Str;

/**
 * Finding and creating customers for staff-made bookings (Day 26).
 * A customer is a user with no role who is not a Super Admin. Search results hide most of the contact details,
 * so the search box cannot be used to read the customer list.
 */
class CustomerDirectory
{
    public const MIN_SEARCH = 3;

    public static function isCustomer(User $user): bool
    {
        return ! $user->is_super && $user->role_id === null;
    }

    /** @return list<array{id: int, name: string, email: string, phone: string|null, bookings: int}> */
    public function search(string $term, int $limit = 8): array
    {
        $term = trim(str_replace(['%', '_'], ' ', $term));
        if (mb_strlen($term) < self::MIN_SEARCH) {
            return [];
        }
        $like = '%' . $term . '%';
        $digits = preg_replace('/\D+/', '', $term);

        $users = User::query()
            ->where('is_super', false)->whereNull('role_id')
            ->where(function ($q) use ($like, $digits) {
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
                if (strlen($digits) >= self::MIN_SEARCH) {
                    $q->orWhere('phone', 'like', '%' . $digits . '%');
                }
            })
            ->orderBy('name')->limit($limit)->get();

        $counts = Booking::query()->whereIn('user_id', $users->pluck('id'))->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $this->maskEmail((string) $u->email),
            'phone' => $u->phone ? '••••' . substr(preg_replace('/\D+/', '', $u->phone), -4) : null,
            'bookings' => (int) ($counts[$u->id] ?? 0),
        ])->all();
    }

    /**
     * Use the customer with this email, or create one. An existing customer is used as it is, never changed.
     * A new customer cannot sign in: the account is inactive and has a random password nobody knows.
     *
     * @return array{0: User, 1: bool} the customer and whether it was just created
     *
     * @throws \DomainException
     */
    public function findOrCreate(string $name, string $email, ?string $phone, ?User $by = null): array
    {
        $email = Str::lower(trim($email));
        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existing) {
            if (! self::isCustomer($existing)) {
                throw new \DomainException('That email address cannot be used for a customer. Use a different one.');
            }

            return [$existing, false];
        }

        $user = new User(['name' => mb_substr(trim(strip_tags($name)), 0, 140), 'email' => $email, 'phone' => $phone ? mb_substr(trim($phone), 0, 30) : null, 'password' => Str::random(48)]);
        $user->forceFill(['role_id' => null, 'is_super' => false, 'is_active' => false])->save();

        SafeAudit::log('customer.created', 'Customer created while making a booking', [
            'subject_id' => $user->id, 'subject_type' => 'User', 'created_by' => $by?->id,
        ]);

        return [$user, true];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $domain === '' ? '••••' : mb_substr($local, 0, 1) . '•••@' . $domain;
    }
}

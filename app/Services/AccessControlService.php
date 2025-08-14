<?php

namespace App\Services;

use App\Models\User;
use App\Models\MusicAccessControl;
use App\Models\TtsCategoryAccess;
use App\Models\SubscriptionPlan;
use App\Models\Product;
use Carbon\Carbon;

class AccessControlService
{
    /**
     * Grant music library access to a user
     */
    public function grantMusicLibraryAccess(User $user, string $accessType, ?Carbon $expiresAt = null, ?string $purchaseReference = null)
    {
        return MusicAccessControl::create([
            'user_id' => $user->id,
            'content_type' => 'music',
            'content_identifier' => 'all_music',
            'access_type' => $accessType,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'purchase_reference' => $purchaseReference,
            'is_active' => true,
        ]);
    }

    /**
     * Grant access to a specific music product
     */
    public function grantMusicProductAccess(User $user, int $productId, string $accessType, ?Carbon $expiresAt = null, ?string $purchaseReference = null)
    {
        return MusicAccessControl::create([
            'user_id' => $user->id,
            'content_type' => 'single_product',
            'content_identifier' => (string)$productId,
            'access_type' => $accessType,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'purchase_reference' => $purchaseReference,
            'is_active' => true,
        ]);
    }

    /**
     * Grant access to a TTS category
     */
    public function grantTtsCategoryAccess(User $user, string $categoryName, string $accessType, ?Carbon $expiresAt = null, ?string $purchaseReference = null, ?float $pricePaid = null)
    {
        return TtsCategoryAccess::updateOrCreate(
            [
                'user_id' => $user->id,
                'category_name' => $categoryName,
            ],
            [
                'access_type' => $accessType,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'purchase_reference' => $purchaseReference,
                'price_paid' => $pricePaid,
                'is_active' => true,
            ]
        );
    }

    /**
     * Grant subscription-based access
     */
    public function grantSubscriptionAccess(User $user, SubscriptionPlan $plan, Carbon $expiresAt, string $subscriptionId)
    {
        $accesses = collect();

        // Grant music library access if included
        if ($plan->includesMusicLibrary()) {
            $accesses->push($this->grantMusicLibraryAccess(
                $user, 
                'subscription', 
                $expiresAt, 
                $subscriptionId
            ));
        }

        // Grant TTS category access
        if ($plan->includesAllTtsCategories()) {
            $allCategories = $this->getAllTtsCategories();
            foreach ($allCategories as $category) {
                $accesses->push($this->grantTtsCategoryAccess(
                    $user, 
                    $category, 
                    'subscription', 
                    $expiresAt, 
                    $subscriptionId,
                    null
                ));
            }
        } else {
            foreach ($plan->getIncludedTtsCategories() as $category) {
                $accesses->push($this->grantTtsCategoryAccess(
                    $user, 
                    $category, 
                    'subscription', 
                    $expiresAt, 
                    $subscriptionId,
                    null
                ));
            }
        }

        return $accesses;
    }

    /**
     * Revoke access when subscription ends or is cancelled
     */
    public function revokeSubscriptionAccess(User $user, string $subscriptionId)
    {
        // Deactivate music access controls
        MusicAccessControl::where('user_id', $user->id)
            ->where('purchase_reference', $subscriptionId)
            ->where('access_type', 'subscription')
            ->update(['is_active' => false]);

        // Deactivate TTS category access
        TtsCategoryAccess::where('user_id', $user->id)
            ->where('purchase_reference', $subscriptionId)
            ->where('access_type', 'subscription')
            ->update(['is_active' => false]);
    }

    /**
     * Check if user can access a specific resource
     */
    public function canUserAccess(User $user, string $resourceType, string $resourceId): array
    {
        $result = [
            'can_access' => false,
            'access_type' => null,
            'expires_at' => null,
            'reason' => null
        ];

        switch ($resourceType) {
            case 'music_library':
                if ($user->hasMusicLibraryAccess()) {
                    $result['can_access'] = true;
                    $result['access_type'] = 'full_library';
                    
                    // Check if access is through subscription
                    $subscription = $user->getActiveSubscription();
                    if ($subscription) {
                        $result['expires_at'] = $subscription->ends_at;
                        $result['access_type'] = 'subscription';
                    }
                } else {
                    $result['reason'] = 'No music library access. Consider purchasing a subscription or individual tracks.';
                }
                break;

            case 'music_product':
                if ($user->hasMusicProductAccess($resourceId)) {
                    $result['can_access'] = true;
                    $result['access_type'] = $user->hasMusicLibraryAccess() ? 'library_access' : 'individual_purchase';
                } else {
                    $result['reason'] = 'This music track requires individual purchase or music library subscription.';
                }
                break;

            case 'tts_category':
                if ($user->hasTtsCategoryAccess($resourceId)) {
                    $result['can_access'] = true;
                    $result['access_type'] = 'category_access';
                    
                    // Check for subscription access
                    $subscription = $user->getActiveSubscription();
                    if ($subscription) {
                        $result['expires_at'] = $subscription->ends_at;
                    }
                } else {
                    $result['reason'] = "Access to '{$resourceId}' category requires individual purchase or subscription.";
                }
                break;
        }

        return $result;
    }

    /**
     * Get all available TTS categories
     */
    public function getAllTtsCategories(): array
    {
        // This could be fetched from your TTS backend or stored in database
        return [
            'Self Confidence',
            'Positive Attitude', 
            'Quit Smoking',
            'Will Power',
            'Guided Visualization',
            'Hypnotherapy for Self Confidence',
            'Sleep Hypnosis',
            'Stress Relief',
            'Motivation',
            'Focus & Concentration'
        ];
    }

    /**
     * Get user's complete access summary
     */
    public function getUserAccessSummary(User $user): array
    {
        return [
            'user_id' => $user->id,
            'music_library' => [
                'has_access' => $user->hasMusicLibraryAccess(),
                'summary' => $user->getMusicAccessSummary()
            ],
            'tts_categories' => [
                'accessible_categories' => $user->getAccessibleTtsCategories(),
                'total_accessible' => count($user->getAccessibleTtsCategories()),
                'total_available' => count($this->getAllTtsCategories())
            ],
            'active_subscription' => $user->getActiveSubscription(),
            'purchased_products' => $user->getPurchasedProducts()->count()
        ];
    }
}

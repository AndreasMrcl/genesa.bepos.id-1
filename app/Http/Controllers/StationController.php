<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StationController extends Controller
{
    public function store(Request $request)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $station = Station::create([
            'name' => $data['name'],
            'is_active' => $request->has('is_active'),
            'store_id' => $userStore->id,
        ]);

        $this->logActivity(
            'Create Station',
            "Adding new station: {$station->name}",
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('storeConfig'))->with('success', 'Station successfully created!');
    }

    public function update(Request $request, $id)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $station = Station::where('id', $id)->firstOrFail();

        $station->update([
            'name' => $data['name'],
            'is_active' => $request->has('is_active'),
        ]);

        $this->logActivity(
            'Update Station',
            "Update station: {$station->name}",
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('storeConfig'))->with('success', 'Station successfully updated!');
    }

    public function destroy($id)
    {
        $userStore = Auth::user()->store;

        $station = Station::where('id', $id)->first();

        if (! $station) {
            return redirect(route('storeConfig'))->withErrors(['msg' => 'Station not found.']);
        }

        $name = $station->name;

        $station->delete();

        $this->logActivity(
            'Delete Station',
            "Deleting station: {$name}",
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('storeConfig'))->with('success', 'Station successfully deleted!');
    }

    private function clearCache(int $storeId): void
    {
        Cache::forget("stations_{$storeId}");

        Cache::forget("menu_{$storeId}");
    }

    private function logActivity($type, $description, $storeId)
    {
        ActivityLog::create([
            'user_id'       => Auth::id(),
            'staff_id'      => Auth::id(),
            'store_id'      => $storeId,
            'activity_type' => $type,
            'description'   => $description,
            'created_at'    => now(),
        ]);

        Cache::forget("activities_{$storeId}");
    }
}

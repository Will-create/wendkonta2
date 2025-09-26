<?php

namespace App\Http\Controllers;

use Redirect;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Input;
use Spatie\Permission\Models\Role;
use Auth;

class LanguageController extends Controller
{
    use \App\Traits\CacheForget;
    
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('language_setting')) {
            return redirect('/dashboard')->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }

        $languages = Language::orderBy('name')->get();

        $defaultLanguage = Language::getDefaultLanguage();
        return view('vendor.translation.languages.index', compact('languages', 'defaultLanguage'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'language' => 'required|unique:languages,language',
            'name' => 'required|string|max:255',
        ]);

        $language = Language::create([
            'language' => $request->language,
            'name' => $request->name,
        ]);

        // forget cached values
        Language::forgetCachedLanguage();
        Translation::forgetCachedTranslations();

        return response()->json([
            'success' => 'Language added successfully.',
            'language' => $language
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'language' => 'required|string',
        ]);

        try {
            Language::where('id', $id)->update([
                'name' => $request->name,
                'language' => $request->language,
            ]);

            // forget cached values
            Language::forgetCachedLanguage();
            Translation::forgetCachedTranslations();
            
            return response()->json(['success' => 'Language updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update the language. Please try again.'], 500);
        }
    }

    public function switchLanguage($id)
    {
        Language::setDefaultLanguage($id);

        return back()->withSuccess('Language switched successfully');
    }

    public function setDefault($id)
    {
        Language::setDefaultLanguage($id);
        
        return response()->json(['success' => 'Default language updated.']);
    }

    public function destroy($id)
    {
        $language = Language::findOrFail($id);
        if (!isset($language)) {
            return response()->json(['error' => 'Language not found!']);
        }
        if ($language->is_default) {
            return response()->json(['error' => 'You can not delete default language!']);
        }

        Translation::where('locale', $language->locale)->delete();

        $language->delete();
        Cache::forget('default_language');
        return response()->json(['success' => 'Language deleted successfully.']);
    }
}

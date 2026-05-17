<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Services\LanguageService;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
       
        $languageService = new LanguageService();
        $lang = $languageService->getCurrent();

        App::setLocale($lang->value);

        return $next($request);
    }
}

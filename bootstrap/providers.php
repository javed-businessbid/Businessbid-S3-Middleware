<?php

use App\Providers\AppServiceProvider;
use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;

return [
    AppServiceProvider::class,
    /*
     * Logo mockup sheets generate PDFs via DomPDF. Register explicitly so bindings
     * exist even when bootstrap/cache/packages.php was not generated (e.g. deploy
     * without composer scripts), which otherwise yields BindingResolutionException
     * for dompdf.wrapper when creating sheets.
     */
    DomPdfServiceProvider::class,
];

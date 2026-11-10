<?php

namespace App\Policies;

/**
 * PAGES-03A: the storefront content pages (App\Models\StorefrontContentPage), registered explicitly in
 * AppServiceProvider. ModelPolicy maps each ability to the role "content-pages.<ability>" (view, edit); a Super
 * Admin passes everything.
 */
class ContentPagePolicy extends ModelPolicy {}

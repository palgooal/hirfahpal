<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * VENDOR-MEDIA-02A: the admin library's upload checks (EnsureSafeMediaUpload: real type sniffed from the bytes,
 * extension of that type, matching image header with dimensions within the pixel cap, full decode) for the vendor
 * media library upload (vendor.dashboard.products.media.store, files[]). Used on that route only, after auth:vendor
 * and vendor.approved.
 *
 * The parent passes a request through unchecked when the admin media policy would refuse it, so its answer stays
 * 403; a vendor is never allowed by that policy, so on this route every uploaded file is always inspected.
 */
class EnsureSafeVendorMediaUpload extends EnsureSafeMediaUpload
{
    protected function shouldInspect(Request $request): bool
    {
        return true;
    }
}

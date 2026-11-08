import React from 'react';
import { Link } from '@inertiajs/react';

const DEFAULT_PAGE_CACHE_DURATION = '10s';

function CachedLink({
    prefetch = 'click',
    cacheFor = DEFAULT_PAGE_CACHE_DURATION,
    preserveState = true,
    preserveScroll = true,
    ...props
}) {
    return (
        <Link
            prefetch={prefetch}
            cacheFor={cacheFor}
            preserveState={preserveState}
            preserveScroll={preserveScroll}
            {...props}
        />
    );
}

export default CachedLink;

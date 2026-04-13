<?php

namespace App\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class CloudflareCspPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT_SRC, ["'self'"])
            ->add(Directive::SCRIPT_SRC, [
                "'self'",
                'https://static.cloudflareinsights.com',
            ])
            ->addNonce(Directive::SCRIPT_SRC)
            ->add(Directive::CONNECT_SRC, [
                "'self'",
                'https://static.cloudflareinsights.com',
            ])
            ->add(Directive::IMG_SRC, [
                "'self'",
                'https://static.cloudflareinsights.com',
            ])
            ->add(Directive::STYLE_SRC, ["'self'"])
            ->addNonce(Directive::STYLE_SRC)
            ->add(Directive::OBJECT_SRC, ["'none'"])
            ->add(Directive::BASE_URI, ["'self'"])
            ->add(Directive::FRAME_ANCESTORS, ["'none'"])
            ->add(Directive::FORM_ACTION, ["'self'"]);
    }
}

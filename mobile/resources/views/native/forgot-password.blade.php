<native:column class="w-full h-full p-6 gap-4 bg-theme-background">
    <native:text class="text-base text-theme-on-surface-variant">{{ __("Enter your email and we'll send you a link to reset your password.") }}</native:text>

    <native:outlined-text-input
        label="{{ __('Email') }}"
        placeholder="you@company.com"
        native:model="email"
        keyboard="email"
        autocapitalize="none"
        :is-error="($fieldErrors['email'] ?? '') !== ''"
        :supporting="$fieldErrors['email'] ?? ''"
        @submit="send"
    />

    @if ($error !== '')
        <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
    @endif

    <native:button label="{{ __('Send reset link') }}" :loading="$busy" @press="send" />
</native:column>

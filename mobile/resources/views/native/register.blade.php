<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-6 gap-4">
        <native:text class="text-base text-theme-on-surface-variant">Track your own hours, leave, and overtime - no employer sign-up needed.</native:text>

        <native:outlined-text-input
            label="Name"
            native:model="name"
            autocapitalize="words"
            :is-error="($fieldErrors['name'] ?? '') !== ''"
            :supporting="$fieldErrors['name'] ?? ''"
        />

        <native:outlined-text-input
            label="Email"
            placeholder="you@example.com"
            trailing-icon="email"
            native:model="email"
            keyboard="email"
            autocapitalize="none"
            :is-error="($fieldErrors['email'] ?? '') !== ''"
            :supporting="$fieldErrors['email'] ?? ''"
        />

        <native:outlined-text-input
            label="Password"
            native:model="password"
            secure
            revealable
            :is-error="($fieldErrors['password'] ?? '') !== ''"
            :supporting="$fieldErrors['password'] ?? 'At least 8 characters.'"
        />

        <native:outlined-text-input
            label="Confirm password"
            native:model="passwordConfirmation"
            secure
            revealable
            :is-error="($fieldErrors['passwordConfirmation'] ?? '') !== ''"
            :supporting="$fieldErrors['passwordConfirmation'] ?? ''"
            @submit="signUp"
        />

        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:button label="Create account" variant="primary" size="lg" :loading="$busy" @press="signUp" />
    </native:column>
</native:scroll-view>

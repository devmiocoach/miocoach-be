<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputField from '@/components/ui/input/InputField.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../components/PageHeader.vue';

defineProps<{
    two_factor_enabled: boolean;
}>();

const disabling = ref(false);
const regenerating = ref(false);
const showDisableDialog = ref(false);
const disablePassword = ref('');

function enableTwoFactor() {
    router.post(route('two-factor.enable'));
}

function disableTwoFactor() {
    disabling.value = true;
    router.delete(route('two-factor.disable'), {
        data: { password: disablePassword.value },
        onFinish: () => {
            disabling.value = false;
            disablePassword.value = '';
            showDisableDialog.value = false;
        },
    });
}

function regenerateCodes() {
    regenerating.value = true;
    router.post(
        route('two-factor.regenerate-codes'),
        {},
        {
            onFinish: () => {
                regenerating.value = false;
            },
        },
    );
}
</script>

<template>
    <SettingsLayout :title="$t('Security')">
        <template #header>
            <PageHeader
                :title="$t('Security')"
                :back-url="route('settings.index')"
            />
        </template>

        <!-- Two-Factor Authentication -->
        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle>{{ $t('Two-Factor Authentication') }}</CardTitle>
                <CardDescription>
                    {{
                        two_factor_enabled
                            ? $t(
                                  'Your account is protected with two-factor authentication.',
                              )
                            : $t(
                                  'Add an extra layer of security to your account by enabling two-factor authentication.',
                              )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-3 w-3 rounded-full"
                            :class="
                                two_factor_enabled
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300 dark:bg-gray-600'
                            "
                        />
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{
                                two_factor_enabled
                                    ? $t('Enabled')
                                    : $t('Disabled')
                            }}
                        </span>
                    </div>

                    <Button
                        v-if="!two_factor_enabled"
                        type="button"
                        @click="enableTwoFactor"
                        data-testid="2fa-enable-button"
                    >
                        {{ $t('Enable') }}
                    </Button>

                    <Button
                        v-else
                        type="button"
                        variant="destructive"
                        @click="showDisableDialog = true"
                        data-testid="2fa-disable-button"
                    >
                        {{ $t('Disable') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Backup Codes -->
        <Card v-if="two_factor_enabled" class="mt-6 max-w-3xl">
            <CardHeader>
                <CardTitle>{{ $t('Backup Codes') }}</CardTitle>
                <CardDescription>
                    {{
                        $t(
                            'Use these codes if you lose access to your authenticator app. Each code can only be used once.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                'Generate new backup codes to replace your existing ones.',
                            )
                        }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        @click="regenerateCodes"
                        :disabled="regenerating"
                        data-testid="2fa-regenerate-codes-button"
                    >
                        {{
                            regenerating
                                ? $t('Generating...')
                                : $t('Generate New Codes')
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>
        <!-- Disable 2FA Confirmation Dialog -->
        <Dialog v-model:open="showDisableDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        $t('Disable Two-Factor Authentication')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Enter your password to confirm you want to disable two-factor authentication.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="disableTwoFactor" class="space-y-4">
                    <InputField
                        name="password"
                        type="password"
                        :label="$t('Password')"
                        :placeholder="$t('Enter your password')"
                        v-model="disablePassword"
                        autocomplete="current-password"
                        required
                        data-testid="2fa-disable-password"
                    />

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDisableDialog = false"
                        >
                            {{ $t('Cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="disabling || !disablePassword"
                            data-testid="2fa-disable-confirm"
                        >
                            {{
                                disabling
                                    ? $t('Disabling...')
                                    : $t('Confirm Disable')
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </SettingsLayout>
</template>

<div x-data="{ 
        show: false,
        googleAccountId: null,
        loading: false
    }" 
    x-show="show" 
    x-cloak
    @open-service-account-modal.window="show = true; googleAccountId = $event.detail.googleAccountId"
    x-on:keydown.escape.window="show = false" 
    class="relative z-50" 
    role="dialog" 
    aria-modal="true">
    {{-- Background backdrop --}}
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:leave="ease-in duration-200"
        class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity" @click="show = false"></div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="show" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6"
                @click.away="show = false">
                <div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Create a Google Service Account</h3>
                        <p class="mt-2 text-sm text-gray-700">
                            To enable direct room booking for Google Workspace accounts, you need to create and upload a service account JSON file.<br><br>
                            Because of security reasons, Google intentionally introduced some complexity to the process. Newer more simple ways of authenticating with Google Workspace API's are unfortunately not yet available. So for now, using a service account is the only way to enable direct room booking for Google Workspace accounts.
                        </p>
                    </div>

                    <div class="mt-4 mb-6 rounded-lg bg-yellow-50 border-l-4 border-yellow-400 p-4 flex items-start gap-3">
                        <svg class="h-5 w-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 9v2m0 4h.01M5.07 20A9.938 9.938 0 0 1 2 12C2 6.48 6.48 2 12 2c5.52 0 10 4.48 10 10a9.938 9.938 0 0 1-3.07 8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="text-left">
                            <div class="font-semibold text-yellow-800">Before you start</div>
                            <div class="text-yellow-800 text-sm mt-1">
                                Make sure you disable the policy preventing the creation of service account keys in your Google Cloud Console.
                                <a href="https://www.youtube.com/watch?v=VY_lrX5iY1U&start=0" target="_blank" class="underline text-yellow-900 hover:text-yellow-700 font-medium">View this video for instructions.</a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="rounded-lg bg-blue-50 p-4">
                            <h4 class="text-sm font-semibold text-blue-900 mb-2">How to get your service account file:</h4>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">
                                <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="underline font-medium">Google Cloud Console</a></li>
                                <li>Create a new project or select an existing one</li>
                                <li>Enable the <strong>Calendar API</strong> in the <strong>APIs & Services</strong> section</li>
                                <li>Navigate to <strong>IAM & Admin</strong> &gt; <strong>Service Accounts</strong></li>
                                <li>Click <strong>Create Service Account</strong></li>
                                    <ul class="list-disc list-inside ml-4 mt-1">
                                        <li>Enter any name for the service account</li>
                                        <li>Click <strong>Done</strong> (you can skip the permissions and principals with access steps)</li>
                                    </ul>
                                <li>Click on the service account you just created to open the details page</li>
                                <li>Go to <strong>Keys</strong> tab and click <strong>Add Key</strong> &gt; <strong>Create new key</strong></li>
                                <li>Select <strong>JSON</strong> format and download the file</li>
                                <li>Copy the Client ID (long digit code 'Unique ID' from service account details page)</li>
                                <li>Head to the <a href="https://admin.google.com/" target="_blank" class="underline font-medium">Google Workspace Admin Console</a></li>
                                <li>Go to <strong>Security</strong> &gt; <strong>Access &amp; control</strong> &gt; <strong>API Controls</strong></li>
                                <li>Click on <strong>Manage domain-wide delegation</strong></li>
                                <li>Click on <strong>Add new</strong></li>
                                <li>Enter the Client ID you copied earlier</li>
                                <li>Add the following scopes:
                                    <ul class="list-disc list-inside ml-4 mt-1">
                                        <li><code class="text-xs">https://www.googleapis.com/auth/calendar.readonly</code></li>
                                        <li><code class="text-xs">https://www.googleapis.com/auth/calendar.events</code></li>
                                    </ul>
                                </li>
                                <li><strong>Important:</strong> After uploading the service account file below, you must share each room calendar with the service account email (found in the JSON file as "client_email") and grant it "Make changes to events" permission. You can do this by:
                                    <ul class="list-disc list-inside ml-4 mt-1">
                                        <li>Opening Google Calendar</li>
                                        <li>Finding your room calendar</li>
                                        <li>Clicking on the calendar settings (three dots)</li>
                                        <li>Selecting "Settings and sharing"</li>
                                        <li>Under "Share with specific people", add the service account email</li>
                                        <li>Grant "Make changes to events" permission</li>
                                    </ul>
                                </li>
                                <li>Upload the service account JSON file you downloaded earlier below</li>
                            </ol>
                        </div>

                        <form x-ref="serviceAccountForm"
                            action="{{ route('google-accounts.service-account') }}"
                            method="POST" 
                            enctype="multipart/form-data"
                            @submit.prevent="loading = true; $refs.serviceAccountForm.submit()" 
                            class="mt-6">
                            @csrf
                            <input type="hidden" name="google_account_id" :value="googleAccountId">
                            
                            <div>
                                <label for="service_account_file" class="block text-sm font-medium leading-6 text-gray-900">
                                    Service Account JSON File
                                </label>
                                <div class="mt-2">
                                    <input 
                                        type="file" 
                                        id="service_account_file" 
                                        name="service_account_file" 
                                        accept=".json"
                                        required
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Upload the JSON key file downloaded from Google Cloud Console.</p>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-x-3">
                                <button type="button" @click="show = false" :disabled="loading"
                                    class="text-sm font-semibold leading-6 text-gray-900 disabled:opacity-50">
                                    Skip for now
                                </button>
                                <button type="submit" :disabled="loading"
                                    class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!loading">Upload</span>
                                    <span x-show="loading">Uploading...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


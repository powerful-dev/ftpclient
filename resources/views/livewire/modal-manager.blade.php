<div>
    @if($activeModal)
        <div class="modal" @click="$dispatch('modal.close')">

            <div class="modal-content middle" @click.stop>

                @if(in_array($activeModal, $allowedModals))
                    @livewire($activeModal, ['payload' => $payload], key($activeModal))
                @endif
            </div>
        </div>
    @endif
</div>

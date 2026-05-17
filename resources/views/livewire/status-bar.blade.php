
<div id="progress-container" wire:poll.500ms="refresh">
    @foreach($tasks as $task)
        <div @class(["progress", $task['status']]) wire:key="task-{{ $task['id'] }}">
            <div class="progress-info d-flex justify-content-between gap-2 fs-12 status-{{ $task['status'] }}">
                <div class="d-flex gap-2">
                    @if (!empty($task['from']))
                        <div>
                            {{ $task['from'] }}
                        </div>
                    @endif
                    @if (!empty($task['to']))
                        <div><i class="arrow-icon"></i></div>
                        <div>{{ $task['to'] }}</div>
                    @endif
                </div>
                <div class="d-flex gap-2">

                    @if ($task['status'] === \App\Enums\TaskStatus::RUNNING->value)
                        
                        <div>
                            <a wire:click="pause('{{ $task['id'] }}')" style="text-decoration:underline; cursor:pointer">Пауза</a>
                        </div>

                        <div>
                            <a wire:click="cancel('{{ $task['id'] }}')" style="text-decoration:underline; cursor:pointer">Отменить</a>
                        </div>
                    @endif

                    @if ($task['status'] === \App\Enums\TaskStatus::PAUSED->value)
                        <div class="paused">
                            <a wire:click="resume('{{ $task['id'] }}')" style="text-decoration:underline; cursor:pointer">Возобновить</a>
                        </div>
                    @endif

                    @php
                        $errors = count($task['errors']);
                    @endphp
                    @if ($errors > 0)
                        <div class="d-flex align-items-center gap-1">
                            <span class="errors" wire:click="showErrors('{{ $task['id'] }}')">Errors: {{ $errors }}</span> 
                        </div>
                    @endif

                    <div class="d-flex align-items-center gap-1">
                        <span>{{ $task['elapsed'] }}</span> 
                        <span>{{ __("Spent") }}</span>
                    </div>

                    @if (!empty($task['eta']))
                        <div class="d-flex align-items-center gap-1">
                            <span>{{ $task['eta'] }}</span>
                        </div>
                    @endif

                    @if (!empty($task['speed']) && $task['speed'] !== '')
                        <div class="d-flex align-items-center gap-1">
                            <span>{{ __("Speed") }}:</span>
                            <span>{{ $task['speed'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if ($showErrorsForStatus == $task['id'])
                <div class="errors-block">
                    @foreach ($task['errors'] as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="progress-bar" style="width: {{ $task['progress'] }}%;">
                <span>{{ $task['label'] }}</span>
            </div>

            <span class="progress-status">
                @if($task['progress'] > 0)
                    {{ $task['progress'] }}%
                @endif
            </span>
        </div>
    @endforeach
</div>
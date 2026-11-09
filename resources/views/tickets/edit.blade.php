@extends('layouts.app')
@section('title', 'Edit '.$ticket->ticket_number)
@section('heading', 'Edit ticket')
@section('actions')
    <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-outline-secondary">Cancel</a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>{{ $ticket->ticket_number }}</h2>
                        <p>Update the request details. Priority and status are managed through the ticket workflow.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" enctype="multipart/form-data" class="panel-body">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                            <select id="category" name="ticket_category_id" class="form-select" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('ticket_category_id', $ticket->ticket_category_id) == $category->id)>
                                        {{ $category->name }}{{ $category->is_active ? '' : ' (inactive; current category)' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ticket_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="subcategory">Subcategory</label>
                            <select id="subcategory" name="ticket_subcategory_id" class="form-select">
                                <option value="">No subcategory</option>
                                @foreach($subcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}" data-category="{{ $subcategory->ticket_category_id }}" @selected(old('ticket_subcategory_id', $ticket->ticket_subcategory_id) == $subcategory->id)>
                                        {{ $subcategory->name }}{{ $subcategory->is_active ? '' : ' (inactive; current subcategory)' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ticket_subcategory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subject">Subject <span class="text-danger">*</span></label>
                            <input id="subject" name="subject" maxlength="180" class="form-control" value="{{ old('subject', $ticket->subject) }}" required>
                            @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Detailed description <span class="text-danger">*</span></label>
                            <textarea id="description" name="description" rows="6" maxlength="10000" class="form-control" required>{{ old('description', $ticket->description) }}</textarea>
                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="building">Building / location</label>
                            <input id="building" name="building" maxlength="100" class="form-control" value="{{ old('building', $ticket->building) }}">
                            @error('building')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="room">Room number</label>
                            <input id="room" name="room_number" maxlength="40" class="form-control" value="{{ old('room_number', $ticket->room_number) }}">
                            @error('room_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="attachment">Add an attachment (optional)</label>
                            <input id="attachment" name="attachment" type="file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.txt,.doc,.docx,.xls,.xlsx">
                            <div class="form-hint mt-1">PDF, image, text, Word or Excel file. Maximum 10 MB. Existing attachments remain on the ticket.</div>
                            @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger mb-0">Please review the highlighted fields and try again.</div></div>
                        @endif
                        <div class="col-12 d-flex gap-2 pt-2">
                            <button class="btn btn-primary">Save ticket details</button>
                            <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </section>
        </div>
        <div class="col-xl-4 mt-3 mt-xl-0">
            <section class="panel panel-body">
                <h2 class="h6 fw-semibold">Workflow fields</h2>
                <p class="text-muted small mb-2">Priority, assignment and status are handled separately so each change has its own history and authorization check.</p>
                <div class="summary-label">Current priority</div>
                <div class="summary-value mb-3">{{ $ticket->priority->name }}</div>
                <div class="summary-label">Current status</div>
                <div class="summary-value">{{ $ticket->status }}</div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const categorySelect = document.getElementById('category');
        const subcategorySelect = document.getElementById('subcategory');
        function filterSubcategories() {
            const selectedCategory = categorySelect.value;
            Array.from(subcategorySelect.options).forEach((option) => {
                if (option.value) option.hidden = option.dataset.category !== selectedCategory;
            });
            if (subcategorySelect.selectedOptions[0]?.hidden) subcategorySelect.value = '';
        }
        categorySelect.addEventListener('change', filterSubcategories);
        filterSubcategories();
    </script>
@endpush

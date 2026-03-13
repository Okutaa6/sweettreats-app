@if (Session::has('success'))
    <div class="alert alert-success alert-dismissable fade show" role="alert">
        <i class="fas fa-check-circle"></i> {!! Session::get('success') !!}
    </div>
@elseif(Session::has('error'))
    <div class="alert alert-danger alert-dismissable fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> {!! Session::get('error') !!}
    </div>
@elseif($errors->any())
    @foreach ($errors->all() as $error)
        <div class="alert alert-danger alert-dismissable fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> {!! $error !!}
        </div>
    @endforeach
@endif

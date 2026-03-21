<form action="/import-teachers" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button type="submit">Import Teachers</button>
</form>

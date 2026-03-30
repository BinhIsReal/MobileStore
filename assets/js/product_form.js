$(document).ready(function () {
    // Thêm dòng ảnh gallery
    document.getElementById('btn-add-gallery').addEventListener('click', function () {
        var wrapper = document.getElementById('gallery-wrapper');
        var div = document.createElement('div');
        div.className = 'gallery-row';
        div.innerHTML = `
            <input type="text" name="gallery[]" class="form-control" placeholder="Link ảnh mới...">
            <button type="button" class="btn-del-gal" onclick="this.parentElement.remove()"><i class="fa fa-trash"></i></button>
        `;
        wrapper.appendChild(div);
    });
});

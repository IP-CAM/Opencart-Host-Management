(function($) {
    const class_btn_remove = 'btn-remove-host';
    const class_error_msg = 'invalid-feedback';
    const $table_body = $('#hosts tbody');

    $(`.${class_btn_remove}`).on('click', function() {
        $(this).closest('tr').remove();
    });

    $('#button-host-add').on('click', function () {
        const $rows = $table_body.find('> tr');
        const $row = $rows.last().clone();
        const $columns = $row.find('> td');
        const $select = $columns.eq(0).find('select').first();
        const $button_remove =
            $(`<button type="button" data-bs-toggle="tooltip" class="btn btn-danger ${class_btn_remove}">
                <i class="fa-solid fa-minus-circle"></i>
            </button>`);

        $select.attr('name', `hosts[${$rows.length}][protocol]`);
        $select.find('option').each(function() {
            $(this).prop('selected', false);
        });
        $columns.eq(0).find(`.${class_error_msg}`).attr('id', `error-protocol-${$rows.length}`);

        $columns.eq(1).find('input').first().attr('name', `hosts[${$rows.length}][hostname]`).val('');
        $columns.eq(1).find(`.${class_error_msg}`).attr('id', `error-hostname-${$rows.length}`);

        $button_remove
            .attr('title', $columns.eq(2).data('btnTitle'))
            .on('click', function() {
                $row.remove();
            });
        $columns.eq(2).children().remove();
        $columns.eq(2).append($button_remove);

        $table_body.append($row);

    });
})(jQuery);
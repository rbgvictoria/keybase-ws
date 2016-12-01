<h1>Test upload</h1>
<?=form_open_multipart()?>
<?=form_hidden('key_id', 8)?>

<p>Load file <?=form_upload('delimitedtext', '') ?></p>
<p><?=form_submit('submit', 'Submit');?></p>
<?=form_close()?>
<?php ob_start();?>
<div class="col-12">
    <div class="container-fluid">
        <div class="row">
            <h1>Product Importer</h1>
            <h4>Import products and variants in woocommerce.</h4>
        </div>
        <?php if (null !== $_GET['status']) {
            echo '<div class="row">';
            if ($_GET['status'] === 'error') {
                if (null !== $_GET['msg']) {
                    if ($_GET['msg'] === 'file_not_found') {
                        echo '<p class="text-danger">Please upload a file and try again.</p>';
                    } else {
                        echo '<p class="text-danger">Unexpected error happen.</p>';
                    }
                }
            } else {
                echo '<p class="text-success">Products created successfully.</p>';
            }
            echo '</div>';
        }?>
        <div class="row">
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <div class="form-control">
                    <label for="product_csv">Products CSV : </label>
                    <input type="file" name="file" required/>
                </div>
                <input type="hidden" name="action" value="woocommerce_product_importer">
                <input type="hidden" name="wp_nonce" value="<?php echo wp_create_nonce( 'woocommerce_product_importer' );?>" ?>
                <div class="form-control">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Upload">
                </div>
            </form>
        </div>
    </div>
</div>
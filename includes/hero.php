<?php 
// Define counts first
$product_count = count($products);
$category_count = count($categories);
?>

<!-- Hero Section -->
<script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js" type="module"></script>

<section style="
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px 20px;
    text-align: center;
    background: #ebebeb;
    z-index: -1;
">
    <!-- Lottie Animation -->
    <dotlottie-wc 
        src="https://lottie.host/58752aa0-d167-4508-b36d-2de886a98f2d/ECDJc9Wf5b.lottie" 
        style="width: 300px; height: 300px;" 
        speed="1" 
        autoplay 
        loop>
    </dotlottie-wc>

    <p style="font-size: 18px; color: #555; max-width: 800px;">
        Discover the latest in audio, input devices, and display technology. Quality gear for professionals and enthusiasts.
    </p>

    <!-- Product Stats Below Animation -->
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 40px; margin-top: 30px; flex-wrap: wrap;">
        <div><strong><?php echo $product_count; ?>+</strong><br>Premium Products</div>
        <div><strong><?php echo $category_count; ?></strong><br>Categories</div>
        <div><strong>3</strong><br>Currencies</div>
    </div>
</section>

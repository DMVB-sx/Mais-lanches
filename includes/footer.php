<footer class="text-center py-8 text-[#5A4E40] text-xs">
        <div class="h-2.5 bg-repeat-x opacity-80 mb-5" style="background-image: linear-gradient(45deg, #241C15 25%, transparent 25%, transparent 75%, #241C15 75%), linear-gradient(45deg, #241C15 25%, transparent 25%, transparent 75%, #241C15 75%); background-size: 14px 14px; background-position: 0 0, 7px 7px;"></div>
        <p><strong><?= htmlspecialchars($estab['nome'] ?? 'Drilavy') ?></strong> • Desenvolvido com Mais-Lanches</p>
    </footer>

    <!-- SweetAlert2 Toast Global Helper -->
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
</body>
</html>

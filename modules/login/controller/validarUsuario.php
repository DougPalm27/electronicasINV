<header>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.28/dist/sweetalert2.all.min.js"></script>
</header>

<?php

$username=$_POST['username'];
$contra=$_POST['password'];

if( ($username == "msorto" && $contra == "proDuX10n_03") || 
    ($username == "dpalma" && $contra == "Hpo051!") || 
    ($username == "mramirez1" && $contra == "\$plan2024")) { // Escapa el signo $
    header("Location: ../../../inicio.php");
    exit(); // O die();
} else {?>


<script type="text/javascript">
            Swal.fire({
                title: "Control de Inventrio",
                text: "Credenciales incorrectas",
                icon: 'error',
                confirmButtonColor: "#3085d6",
                confirmButtonText: "Volver a intentar",
            }).then((result) => {
                if (result) {
                    window.location = '../../../index.php';
                }
            })
</script>


<?php

}
?>
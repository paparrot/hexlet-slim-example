<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
  <title>Slim CRUD</title>
</head>
<body>
  <header class="bg-primary">
      <nav class="navbar navbar-dark d-flex">
        <div class="container">
          <a href="/" class="navbar-brand mb-0 h1">Slim CRUD</a>
          <?php if(isset($_SESSION['isAdmin'])): ?>
          <form action="/session" method="post">
            <input type="hidden" name="_METHOD" value="DELETE">
            <button type="submit" class="btn btn-light">Выход</button>
          </form>
        <?php endif; ?>
        </div>
      </nav>
  </header>
  <main class="container">
    <div class="mt-3">
      <?= $content ?>
    </div>
  </main>
</body>
</html>
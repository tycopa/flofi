</main>
<footer class="footer">
  <p>
    <?= date('Y') ?> <?= h(APP_NAME) ?>. All rights reserved.
    <a href="reset.php">Forgot Password?</a>
    <a href="mailto:<?= MAIL_FROM ?>">Support</a>
  </p>
</footer>
</body>
</html>

<style>
.footer {
  text-align:center;
  padding:14px;
  font-size:13px;
  color:#666;
  border-top:2px solid var(--gray);
  margin-top:30px;
}
.footer p {
  margin:0;
  display:flex;
  flex-direction:column;
  gap:2px;
  line-height:1.4;
}
.footer a {
  font-weight:600;
  border-bottom:none;
}
</style>

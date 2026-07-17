// Footer — подвал сайта: копирайт разработчика. Год подставляется автоматически.
export default function Footer() {
  const year = new Date().getFullYear()

  return (
    <footer className="app-footer">
      © {year} · Developed by Deem Moor
    </footer>
  )
}

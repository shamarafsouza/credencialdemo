# credencialdemo
# Sistema de Credenciais de Estacionamento

Sistema web desenvolvido para automatizar o processo de emissão e gerenciamento de credenciais de estacionamento destinadas a pessoas idosas e pessoas com deficiência (PCD).

O projeto foi desenvolvido com o objetivo de transformar um processo que anteriormente dependia de etapas manuais em um fluxo digital, facilitando o cadastro, a validação das informações, a geração da credencial e seu gerenciamento.

> **⚠️ Versão demonstrativa**
>
> Este repositório contém uma versão preparada exclusivamente para demonstração pública.
> Todos os dados presentes no sistema são fictícios e foram criados para fins de demonstração.
> Nenhum dado pessoal real é utilizado nesta versão.

---

## ✨ Funcionalidades

- 🔐 Sistema de autenticação para acesso à aplicação
- 📝 Cadastro de beneficiários
- 🔎 Validação das informações cadastradas
- 🪪 Emissão automatizada de credenciais
- 🔢 Geração automática do número da credencial
- 📄 Geração de credencial em PDF
- ✍️ Assinatura digital da credencial
- 📋 Listagem e gerenciamento das credenciais emitidas
- 📥 Download da credencial em PDF
- 🗃️ Armazenamento dos dados em banco SQLite
- 🎯 Interface voltada para simplificação do processo administrativo

---

## 🛠️ Tecnologias utilizadas

### Backend
- PHP

### Banco de dados
- SQLite

### Frontend
- HTML5
- CSS3
- JavaScript

### Geração de documentos
- FPDF
- FPDI

---

## 🔄 Fluxo do sistema

O sistema foi desenvolvido para organizar o processo de emissão da credencial em etapas:

```text
Login
  ↓
Cadastro do beneficiário
  ↓
Validação das informações
  ↓
Emissão da credencial
  ↓
Geração do documento
  ↓
Assinatura digital
  ↓
Credencial emitida
  ↓
Download do PDF

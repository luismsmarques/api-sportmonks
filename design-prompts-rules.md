# 🎨 Prompt para Design Premium Super Portistas

Este documento contém o prompt completo para aplicar o design premium Super Portistas a qualquer componente futuro.

## 🎯 **Prompt Principal**

```
Aplica o design premium Super Portistas ao componente [NOME_DO_COMPONENTE]. 

Seguir este padrão de design:

### 🎯 **Estrutura Base:**
- Container: `bg-gradient-to-br from-white to-[COR]-50 rounded-xl shadow-lg border border-gray-200 p-8 mb-8 relative overflow-hidden`
- Background Pattern: SVG decorativo animado no canto superior direito com opacity-5
- Header: Ícone gradiente + título + descrição + contador
- Conteúdo: Com z-index relativo para sobreposição

### 🌈 **Sistema de Cores por Componente:**
- Article Reactions: `blue-50` + gradiente azul
- Social Share: `green-50` + gradiente verde  
- Comments: `purple-50` + gradiente roxo
- [NOVO]: Escolher cor temática apropriada

### 🎭 **Elementos Visuais:**
- Header com ícone: `w-12 h-12 bg-gradient-to-br from-[COR]-500 to-[COR]-600 rounded-xl`
- Título: `text-2xl font-bold text-gray-900`
- Descrição: `text-sm text-gray-600`
- Contador: `text-lg font-bold text-[COR]-600`

### ⚡ **Animações CSS:**
- Background pattern: `@keyframes float-[nome]` com movimento suave
- Hover effects: `transform: translateY(-2px) scale(1.05)`
- Focus states: `ring-4 ring-[COR]-300`
- Transições: `transition-all duration-300`

### 📱 **Responsive:**
- Mobile: Padding reduzido, grid adaptativo, tamanhos ajustados
- Breakpoints: 768px e 480px

### 🎨 **Estilo Premium:**
- Gradientes sofisticados
- Sombras dramáticas
- Bordas arredondadas (rounded-xl)
- Backdrop blur effects
- Estados de loading/success

Aplicar este padrão mantendo a funcionalidade existente e melhorando a experiência visual.
```

## 📋 **Sistema de Cores Temáticas**

### 🎨 **Cores Disponíveis:**

| Cor | Classe Tailwind | Uso Sugerido |
|-----|----------------|--------------|
| 🔵 **Azul** | `blue-50` | Article Reactions, Stats, Analytics |
| 🟢 **Verde** | `green-50` | Social Share, Success, Growth |
| 🟣 **Roxo** | `purple-50` | Comments, Community, Discussion |
| 🟠 **Laranja** | `orange-50` | Alerts, Warnings, Highlights |
| 🔴 **Vermelho** | `red-50` | Errors, Urgent, Important |
| 🟡 **Amarelo** | `yellow-50` | Featured, Premium, Special |
| 🔷 **Índigo** | `indigo-50` | Professional, Business |
| 🌸 **Rosa** | `pink-50` | Creative, Fun, Entertainment |

## 🏗️ **Estrutura HTML Template**

```html
<div class="bg-gradient-to-br from-white to-[COR]-50 rounded-xl shadow-lg border border-gray-200 p-8 mb-8 relative overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute top-0 right-0 w-32 h-32 opacity-5">
        <svg viewBox="0 0 100 100" class="w-full h-full text-[COR]-600">
            <!-- SVG Pattern específico -->
        </svg>
    </div>
    
    <!-- Header -->
    <div class="flex items-center mb-8 relative z-10">
        <div class="w-12 h-12 bg-gradient-to-br from-[COR]-500 to-[COR]-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
            <!-- Ícone SVG -->
        </div>
        <div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1">Título do Componente</h3>
            <p class="text-sm text-gray-600">Descrição do componente</p>
            <div class="flex items-center mt-2">
                <span class="text-lg font-bold text-[COR]-600">Contador</span>
                <span class="text-sm text-gray-500 ml-1">unidade</span>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="relative z-10">
        <!-- Conteúdo específico do componente -->
    </div>
</div>
```

## 🎨 **CSS Template**

```css
/* Super Portistas [NOME_COMPONENTE] Premium Styles */
.bg-gradient-to-br {
    background: linear-gradient(135deg, #ffffff 0%, [COR_HEX] 100%);
}

/* Background pattern animation */
@keyframes float-[nome] {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-10px) rotate([GRAUS]deg);
    }
}

.absolute.top-0.right-0 svg {
    animation: float-[nome] [DURACAO]s ease-in-out infinite;
}

/* Hover effects */
.component-element:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

/* Focus states */
.component-element:focus {
    outline: none;
    ring: 4px;
    ring-color: rgba([COR_RGB], 0.3);
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .p-8 {
        padding: 1.5rem;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .p-8 {
        padding: 1rem;
    }
}
```

## 🎯 **Exemplos de Uso**

### **Newsletter Signup:**
```
"Aplica o design premium Super Portistas ao componente Newsletter Signup usando cor laranja (orange-50) para destacar a importância da subscrição."
```

### **User Profile:**
```
"Aplica o design premium Super Portistas ao componente User Profile usando cor índigo (indigo-50) para um visual profissional."
```

### **Statistics Dashboard:**
```
"Aplica o design premium Super Portistas ao componente Statistics Dashboard usando cor azul (blue-50) para dados e analytics."
```

## 🔧 **Checklist de Implementação**

- [ ] **Estrutura Base**: Container com gradiente e overflow-hidden
- [ ] **Background Pattern**: SVG animado no canto superior direito
- [ ] **Header**: Ícone gradiente + título + descrição + contador
- [ ] **Cores Temáticas**: Escolher cor apropriada para o componente
- [ ] **Animações**: Hover effects e transições suaves
- [ ] **Responsive**: Adaptações para mobile
- [ ] **Acessibilidade**: Focus states e contrastes adequados
- [ ] **Estados**: Loading, success, error quando aplicável

## 📝 **Notas Importantes**

1. **Consistência**: Manter sempre o mesmo padrão visual
2. **Funcionalidade**: Não quebrar funcionalidades existentes
3. **Performance**: Animações otimizadas e CSS eficiente
4. **Acessibilidade**: Sempre incluir focus states e contrastes
5. **Mobile First**: Design responsivo desde o início

## 🚀 **Resultado Esperado**

Cada componente deve ter:
- ✅ Design premium consistente
- ✅ Identidade visual única através da cor temática
- ✅ Animações sofisticadas
- ✅ Experiência mobile perfeita
- ✅ Acessibilidade completa
- ✅ Performance otimizada

---

**Criado para o projeto Super Portistas**  
*Design System Premium v1.0*
